<?php

namespace App\Domain\Analytics\Queries;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Analytics\ValueObjects\AnalyticsSnapshot;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class AnalyticsQueryService
{
    public function for(User $user, ReportPeriod $period, ?Project $project = null): AnalyticsSnapshot
    {
        $tasks = Task::query()->ownedBy($user)->whereNull('parent_task_id')->when($project, fn ($query) => $query->where('project_id', $project->getKey()))->with('project')->get();
        $taskIds = $tasks->modelKeys();
        $activities = TaskActivity::query()->ownedBy($user)->whereIn('task_id', $taskIds)->where('created_at', '>=', $period->start)->where('created_at', '<', $period->end)->with('task')->orderBy('created_at')->orderBy('id')->get();
        $activities = $activities->filter(fn (TaskActivity $activity): bool => $activity->task !== null && ! $activity->task->trashed());

        $events = collect([
            'created' => TaskActivityType::TASK_CREATED,
            'completed' => TaskActivityType::STATUS_CHANGED,
            'started' => TaskActivityType::STATUS_CHANGED,
            'reviewed' => TaskActivityType::STATUS_CHANGED,
            'blocked' => TaskActivityType::STATUS_CHANGED,
            'reopened' => TaskActivityType::STATUS_CHANGED,
        ])->mapWithKeys(fn (TaskActivityType $type, string $name): array => [$name => collect()]);

        foreach ($activities as $activity) {
            $status = $this->status($activity->new_value);
            $oldStatus = $this->status($activity->old_value);
            if ($activity->event_type === TaskActivityType::TASK_CREATED) {
                $events['created']->push($activity->task_id);
            }
            if ($activity->event_type === TaskActivityType::STATUS_CHANGED) {
                if ($status === TaskStatus::DONE->value) {
                    $events['completed']->push($activity->task_id);
                }
                foreach ([TaskStatus::IN_PROGRESS->value => 'started', TaskStatus::IN_REVIEW->value => 'reviewed', TaskStatus::BLOCKED->value => 'blocked'] as $target => $name) {
                    if ($status === $target) {
                        $events[$name]->push($activity->task_id);
                    }
                }
                if (in_array($oldStatus, [TaskStatus::DONE->value, TaskStatus::CANCELLED->value], true) && ! in_array($status, [TaskStatus::DONE->value, TaskStatus::CANCELLED->value], true)) {
                    $events['reopened']->push($activity->task_id);
                }
            }
        }

        $throughput = $events->map(fn (Collection $ids): int => $ids->unique()->count())->all();
        $completionEvents = $activities->filter(fn (TaskActivity $activity): bool => $activity->event_type === TaskActivityType::STATUS_CHANGED && $this->status($activity->new_value) === TaskStatus::DONE->value)->groupBy('task_id')->map(fn (Collection $items): TaskActivity => $items->first());
        $leadTimes = [];
        $cycleTimes = [];
        foreach ($completionEvents as $taskId => $completion) {
            $task = $tasks->firstWhere('id', $taskId);
            if ($task?->created_at) {
                $leadTimes[] = $task->created_at->diffInMinutes($completion->created_at) / 60;
            }
            if ($task?->first_started_at) {
                $cycleTimes[] = $task->first_started_at->diffInMinutes($completion->created_at) / 60;
            }
        }

        return new AnalyticsSnapshot(
            reportPeriod: $period,
            throughput: $throughput,
            leadTimeMedianHours: $this->median($leadTimes),
            cycleTimeMedianHours: $this->median($cycleTimes),
            timeInStatus: $this->timeInStatus($activities, $period),
            projectContribution: $this->projectContribution($tasks),
        );
    }

    private function status(mixed $value): ?string
    {
        return is_array($value) ? ($value['status'] ?? $value['value'] ?? null) : null;
    }

    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? (float) $values[$middle] : (float) (($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function timeInStatus(EloquentCollection $activities, ReportPeriod $period): array
    {
        $totals = array_fill_keys([TaskStatus::BACKLOG->value, TaskStatus::NOT_STARTED->value, TaskStatus::IN_PROGRESS->value, TaskStatus::IN_REVIEW->value, TaskStatus::BLOCKED->value, TaskStatus::DONE->value], 0.0);
        foreach ($activities->groupBy('task_id') as $taskActivities) {
            $status = TaskStatus::NOT_STARTED->value;
            $cursor = $period->start;
            foreach ($taskActivities as $activity) {
                $minutes = $cursor->diffInMinutes($activity->created_at);
                if (array_key_exists($status, $totals)) {
                    $totals[$status] += max(0, $minutes) / 60;
                }
                $status = $this->status($activity->new_value) ?? $status;
                $cursor = $activity->created_at;
            }
            if (array_key_exists($status, $totals)) {
                $totals[$status] += max(0, $cursor->diffInMinutes($period->end)) / 60;
            }
        }

        return $totals;
    }

    private function projectContribution(EloquentCollection $tasks): Collection
    {
        return $tasks->groupBy('project_id')->map(function (EloquentCollection $projectTasks, int|string $projectId): array {
            $eligible = $projectTasks->where('status', '!=', TaskStatus::CANCELLED->value)->count();
            $completed = $projectTasks->where('status', TaskStatus::DONE->value)->count();

            return ['project_id' => (int) $projectId, 'project' => $projectTasks->first()->project?->name, 'completed' => $completed, 'eligible' => $eligible, 'progress' => $eligible === 0 ? 0 : round(($completed / $eligible) * 100, 2)];
        })->values();
    }
}
