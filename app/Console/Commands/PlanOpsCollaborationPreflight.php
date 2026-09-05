<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PlanOpsCollaborationPreflight extends Command
{
    protected $signature = 'planops:collaboration-preflight {--json : Emit machine-readable JSON}';

    protected $description = 'Report collaboration migration anomalies without changing data';

    public function handle(): int
    {
        $report = [
            'missing_project_owners' => $this->missingProjectOwners(),
            'duplicate_project_keys' => $this->duplicateProjectKeys(),
            'duplicate_task_numbers' => $this->duplicateTaskNumbers(),
            'orphaned_tasks' => $this->orphanedTasks(),
            'orphaned_activities' => $this->orphanedActivities(),
            'orphaned_labels' => $this->orphanedLabels(),
            'duplicate_project_labels' => $this->duplicateProjectLabels(),
            'legacy_labels_spanning_projects' => $this->legacyLabelsSpanningProjects(),
            'invalid_assignees' => $this->invalidAssignees(),
            'existing_activity_rows' => $this->existingActivityRows(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Anomaly', 'Count', 'Sample IDs'], collect($report)->map(
                fn (array $value, string $key): array => [$key, $value['count'], implode(', ', $value['sample_ids'])]
            )->all());
        }

        return self::SUCCESS;
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function missingProjectOwners(): array
    {
        $query = DB::table('projects')
            ->leftJoin('users', 'users.id', '=', 'projects.owner_id')
            ->where(function ($query): void {
                $query->whereNull('projects.owner_id')->orWhereNull('users.id');
            });

        return $this->result($query, 'projects.id');
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function duplicateProjectKeys(): array
    {
        $groups = DB::table('projects')
            ->selectRaw('LOWER(key) AS normalized_key')
            ->groupByRaw('LOWER(key)')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('normalized_key')
            ->get();
        $ids = $groups->flatMap(fn (object $group): array => DB::table('projects')
            ->whereRaw('LOWER(key) = ?', [$group->normalized_key])
            ->orderBy('id')
            ->pluck('id')
            ->all())->values()->all();

        return ['count' => $groups->count(), 'sample_ids' => array_slice($ids, 0, 20)];
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function duplicateTaskNumbers(): array
    {
        $groups = DB::table('tasks')
            ->select('project_id', 'number')
            ->groupBy('project_id', 'number')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('project_id')
            ->orderBy('number')
            ->get();
        $ids = $groups->flatMap(fn (object $group): array => DB::table('tasks')
            ->where('project_id', $group->project_id)
            ->where('number', $group->number)
            ->orderBy('id')
            ->pluck('id')
            ->all())->values()->all();

        return ['count' => $groups->count(), 'sample_ids' => array_slice($ids, 0, 20)];
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function orphanedTasks(): array
    {
        return $this->result(DB::table('tasks')->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')->whereNull('projects.id'), 'tasks.id');
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function orphanedActivities(): array
    {
        return $this->result(DB::table('task_activities')->leftJoin('tasks', 'tasks.id', '=', 'task_activities.task_id')->whereNull('tasks.id'), 'task_activities.id');
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function orphanedLabels(): array
    {
        return $this->result(DB::table('labels')->whereNotNull('project_id')->leftJoin('projects', 'projects.id', '=', 'labels.project_id')->whereNull('projects.id'), 'labels.id');
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function duplicateProjectLabels(): array
    {
        $groups = DB::table('labels')
            ->whereNotNull('project_id')
            ->select('project_id', 'normalized_name')
            ->groupBy('project_id', 'normalized_name')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('project_id')
            ->orderBy('normalized_name')
            ->get();
        $ids = $groups->flatMap(fn (object $group): array => DB::table('labels')
            ->where('project_id', $group->project_id)
            ->where('normalized_name', $group->normalized_name)
            ->orderBy('id')
            ->pluck('id')
            ->all())->values()->all();

        return ['count' => $groups->count(), 'sample_ids' => array_slice($ids, 0, 20)];
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function legacyLabelsSpanningProjects(): array
    {
        $groups = DB::table('labels')
            ->join('task_label', 'task_label.label_id', '=', 'labels.id')
            ->join('tasks', 'tasks.id', '=', 'task_label.task_id')
            ->whereNull('labels.project_id')
            ->select('labels.id')
            ->groupBy('labels.id')
            ->havingRaw('COUNT(DISTINCT tasks.project_id) > 1')
            ->orderBy('labels.id')
            ->pluck('labels.id');

        return ['count' => $groups->count(), 'sample_ids' => $groups->take(20)->values()->all()];
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function invalidAssignees(): array
    {
        return $this->result(DB::table('tasks')
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->leftJoin('project_memberships', function ($join): void {
                $join->on('project_memberships.project_id', '=', 'tasks.project_id')
                    ->on('project_memberships.user_id', '=', 'tasks.assignee_id')
                    ->whereNull('project_memberships.removed_at');
            })
            ->whereNotNull('tasks.assignee_id')
            ->whereNull('project_memberships.id'), 'tasks.id');
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function existingActivityRows(): array
    {
        return $this->result(DB::table('task_activities')->select('task_id')->distinct(), 'task_id');
    }

    /** @return array{count: int, sample_ids: list<int>} */
    private function result(object $query, string $idColumn): array
    {
        $ids = $query->orderBy($idColumn)->pluck($idColumn);

        return ['count' => $ids->count(), 'sample_ids' => $ids->take(20)->values()->all()];
    }
}
