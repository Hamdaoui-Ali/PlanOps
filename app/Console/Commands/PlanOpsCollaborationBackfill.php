<?php

namespace App\Console\Commands;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PlanOpsCollaborationBackfill extends Command
{
    protected $signature = 'planops:collaboration-backfill {--chunk=500} {--dry-run : Report planned changes without writing}';

    protected $description = 'Backfill collaboration identities and memberships from legacy PlanOps data';

    public function handle(): int
    {
        $this->assertProjectKeysAreUnambiguous();

        $report = [
            'dry_run' => (bool) $this->option('dry-run'),
            'projects_updated' => 0,
            'memberships_upserted' => 0,
            'tasks_updated' => 0,
            'activities_updated' => 0,
            'labels_updated' => 0,
            'labels_duplicated' => 0,
        ];
        $chunk = max(1, (int) $this->option('chunk'));

        Project::query()->orderBy('id')->chunkById($chunk, function ($projects) use (&$report): void {
            foreach ($projects as $project) {
                if ($report['dry_run']) {
                    $this->previewProject($project, $report);

                    continue;
                }

                DB::transaction(function () use ($project, &$report): void {
                    $this->backfillProject($project, $report);
                });
            }
        });

        if (! $report['dry_run']) {
            $this->backfillTasks($chunk, $report);
            $this->backfillActivities($chunk, $report);
            $this->backfillLabels($chunk, $report);
        }

        $this->line(json_encode($report, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function assertProjectKeysAreUnambiguous(): void
    {
        $duplicate = DB::table('projects')
            ->selectRaw('LOWER(TRIM(key)) AS normalized_key')
            ->groupByRaw('LOWER(TRIM(key))')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('normalized_key')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException("Cannot backfill until project key collision is resolved: {$duplicate->normalized_key}");
        }
    }

    private function previewProject(Project $project, array &$report): void
    {
        if ($project->owner_id !== $project->user_id || trim($project->key) !== $project->key || strtoupper($project->key) !== $project->key) {
            $report['projects_updated']++;
        }

        if (! ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $project->user_id)->where('role', ProjectRole::OWNER)->whereNull('removed_at')->exists()) {
            $report['memberships_upserted']++;
        }
    }

    private function backfillProject(Project $project, array &$report): void
    {
        $canonicalKey = strtoupper(trim($project->key));
        if ($project->owner_id !== $project->user_id || $project->key !== $canonicalKey) {
            $project->forceFill(['owner_id' => $project->user_id, 'key' => $canonicalKey])->save();
            $report['projects_updated']++;
        }

        $membership = ProjectMembership::query()
            ->where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->first();
        $needsSave = $membership === null
            || $membership->role !== ProjectRole::OWNER
            || $membership->removed_at !== null;

        if ($membership === null) {
            $membership = new ProjectMembership;
            $membership->project_id = $project->id;
            $membership->user_id = $project->user_id;
        }

        if ($needsSave) {
            $membership->forceFill([
                'role' => ProjectRole::OWNER,
                'joined_at' => $membership->joined_at ?? $project->created_at,
                'removed_at' => null,
                'removed_by_user_id' => null,
            ])->save();
            $report['memberships_upserted']++;
        }
    }

    private function backfillTasks(int $chunk, array &$report): void
    {
        Task::query()->where(function ($query): void {
            $query->whereNull('created_by_user_id')->orWhereNull('assignee_id');
        })->orderBy('id')->chunkById($chunk, function ($tasks) use (&$report): void {
            foreach ($tasks as $task) {
                $changes = [];
                if ($task->created_by_user_id === null) {
                    $changes['created_by_user_id'] = $task->user_id;
                }
                if ($task->assignee_id === null) {
                    $changes['assignee_id'] = $task->user_id;
                }
                if ($changes !== []) {
                    Task::query()->whereKey($task->id)->update($changes);
                    $report['tasks_updated']++;
                }
            }
        });
    }

    private function backfillActivities(int $chunk, array &$report): void
    {
        DB::table('task_activities')->whereNull('actor_user_id')->orderBy('id')->chunkById($chunk, function ($activities) use (&$report): void {
            foreach ($activities as $activity) {
                DB::table('task_activities')->where('id', $activity->id)->update(['actor_user_id' => $activity->user_id]);
                $report['activities_updated']++;
            }
        });
    }

    private function backfillLabels(int $chunk, array &$report): void
    {
        Label::query()->whereNull('project_id')->orderBy('id')->chunkById($chunk, function ($labels) use (&$report): void {
            foreach ($labels as $label) {
                $projects = DB::table('task_label')
                    ->join('tasks', 'tasks.id', '=', 'task_label.task_id')
                    ->where('task_label.label_id', $label->id)
                    ->orderBy('tasks.project_id')
                    ->distinct()
                    ->pluck('tasks.project_id');

                if ($projects->count() === 0) {
                    continue;
                }

                $firstProject = $projects->first();
                Label::query()->whereKey($label->id)->update(['project_id' => $firstProject]);
                $report['labels_updated']++;

                foreach ($projects->skip(1) as $projectId) {
                    $copy = Label::query()->firstOrCreate([
                        'project_id' => $projectId,
                        'normalized_name' => $label->normalized_name,
                    ], [
                        'user_id' => $label->user_id,
                        'name' => $label->name,
                        'color' => $label->color,
                    ]);
                    DB::table('task_label')
                        ->where('label_id', $label->id)
                        ->whereIn('task_id', DB::table('tasks')->where('project_id', $projectId)->pluck('id'))
                        ->update(['label_id' => $copy->id]);
                    $report['labels_duplicated']++;
                }
            }
        });
    }
}
