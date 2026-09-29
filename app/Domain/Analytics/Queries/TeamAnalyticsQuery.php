<?php

namespace App\Domain\Analytics\Queries;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Analytics\ValueObjects\TeamAnalyticsSnapshot;
use App\Domain\Identity\Enums\WeekStartDay;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class TeamAnalyticsQuery
{
    public function for(User $viewer, Project $project, ReportPeriod $period, ?CarbonImmutable $now = null): TeamAnalyticsSnapshot
    {
        $project = Project::query()
            ->detailedReportsVisibleTo($viewer)
            ->whereKey($project->getKey())
            ->firstOrFail();

        $tasks = Task::query()
            ->where('project_id', $project->getKey())
            ->whereNull('parent_task_id')
            ->get(['id', 'status', 'due_on', 'assignee_id']);
        $taskIds = $tasks->modelKeys();
        $activities = $taskIds === []
            ? new EloquentCollection
            : TaskActivity::query()
                ->whereIn('task_id', $taskIds)
                ->where('created_at', '>=', $period->start)
                ->where('created_at', '<', $period->end)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

        $throughput = $this->throughput($activities);
        $timezone = $viewer->preference?->timezone ?? 'Africa/Casablanca';

        return new TeamAnalyticsSnapshot(
            project: $project,
            reportPeriod: $period,
            throughput: $throughput,
            statusDistribution: $this->statusDistribution($tasks),
            currentWorkload: $this->currentWorkload($tasks, $viewer, $now),
            weeklyFlow: $this->weeklyFlow($activities, $period, $viewer, $timezone),
            hasRecordedMovement: array_sum($throughput) > 0,
        );
    }

    /**
     * @return array<string, int>
     */
    private function throughput(EloquentCollection $activities): array
    {
        $eventIds = array_fill_keys(['created', 'completed', 'started', 'reviewed', 'blocked', 'reopened'], []);

        foreach ($activities as $activity) {
            if ($activity->event_type === TaskActivityType::TASK_CREATED) {
                $eventIds['created'][] = $activity->task_id;
            }

            if ($activity->event_type !== TaskActivityType::STATUS_CHANGED) {
                continue;
            }

            $status = $this->status($activity->new_value);
            $oldStatus = $this->status($activity->old_value);

            if ($status === TaskStatus::DONE->value) {
                $eventIds['completed'][] = $activity->task_id;
            }

            foreach ([
                TaskStatus::IN_PROGRESS->value => 'started',
                TaskStatus::IN_REVIEW->value => 'reviewed',
                TaskStatus::BLOCKED->value => 'blocked',
            ] as $target => $name) {
                if ($status === $target) {
                    $eventIds[$name][] = $activity->task_id;
                }
            }

            if (in_array($oldStatus, [TaskStatus::DONE->value, TaskStatus::CANCELLED->value], true)
                && ! in_array($status, [TaskStatus::DONE->value, TaskStatus::CANCELLED->value], true)) {
                $eventIds['reopened'][] = $activity->task_id;
            }
        }

        return collect($eventIds)->map(fn (array $ids): int => count(array_unique($ids)))->all();
    }

    /**
     * @return array<string, int>
     */
    private function statusDistribution(EloquentCollection $tasks): array
    {
        $distribution = array_fill_keys([
            TaskStatus::BACKLOG->value,
            TaskStatus::NOT_STARTED->value,
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::IN_REVIEW->value,
            TaskStatus::BLOCKED->value,
            TaskStatus::DONE->value,
        ], 0);

        foreach ($tasks as $task) {
            if (isset($distribution[$task->status->value])) {
                $distribution[$task->status->value]++;
            }
        }

        return $distribution;
    }

    /**
     * @return array{active: int, blocked: int, overdue: int, unassigned: int}
     */
    private function currentWorkload(EloquentCollection $tasks, User $viewer, ?CarbonImmutable $now): array
    {
        $timezone = $viewer->preference?->timezone ?? 'Africa/Casablanca';
        $localToday = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $workload = ['active' => 0, 'blocked' => 0, 'overdue' => 0, 'unassigned' => 0];

        foreach ($tasks as $task) {
            if (in_array($task->status, [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW], true)) {
                $workload['active']++;
            }

            if ($task->status === TaskStatus::BLOCKED) {
                $workload['blocked']++;
            }

            if ($task->isOverdueOn($localToday)) {
                $workload['overdue']++;
            }

            if ($task->assignee_id === null && ! in_array($task->status, [TaskStatus::DONE, TaskStatus::CANCELLED], true)) {
                $workload['unassigned']++;
            }
        }

        return $workload;
    }

    /**
     * @return Collection<int, array{label: string, created: int, completed: int, blocked: int}>
     */
    private function weeklyFlow(EloquentCollection $activities, ReportPeriod $period, User $viewer, string $timezone): Collection
    {
        $weekStartDay = ($viewer->preference?->week_start_day ?? WeekStartDay::MONDAY) === WeekStartDay::SUNDAY
            ? CarbonInterface::SUNDAY
            : CarbonInterface::MONDAY;
        $localStart = $period->start->setTimezone($timezone);
        $localEnd = $period->end->setTimezone($timezone);
        $cursor = $localStart->startOfWeek($weekStartDay);
        $buckets = [];
        $bucketTaskIds = [];

        while ($cursor->lessThan($localEnd)) {
            $key = $cursor->toDateString();
            $buckets[$key] = [
                'label' => $cursor->format('M j, Y'),
            ];
            $bucketTaskIds[$key] = ['created' => [], 'completed' => [], 'blocked' => []];
            $cursor = $cursor->addWeek();
        }

        foreach ($activities as $activity) {
            $eventLocal = $activity->created_at->setTimezone($timezone);
            $bucketKey = $eventLocal->startOfWeek($weekStartDay)->toDateString();
            if (! isset($buckets[$bucketKey])) {
                continue;
            }

            if ($activity->event_type === TaskActivityType::TASK_CREATED) {
                $bucketTaskIds[$bucketKey]['created'][$activity->task_id] = true;
                continue;
            }

            if ($activity->event_type !== TaskActivityType::STATUS_CHANGED) {
                continue;
            }

            $status = $this->status($activity->new_value);
            if ($status === TaskStatus::DONE->value) {
                $bucketTaskIds[$bucketKey]['completed'][$activity->task_id] = true;
            }
            if ($status === TaskStatus::BLOCKED->value) {
                $bucketTaskIds[$bucketKey]['blocked'][$activity->task_id] = true;
            }
        }

        return collect(array_map(function (string $key) use ($buckets, $bucketTaskIds): array {
            return [
                'label' => $buckets[$key]['label'],
                'created' => count($bucketTaskIds[$key]['created']),
                'completed' => count($bucketTaskIds[$key]['completed']),
                'blocked' => count($bucketTaskIds[$key]['blocked']),
            ];
        }, array_keys($buckets)));
    }

    private function status(mixed $value): ?string
    {
        return is_array($value) ? ($value['status'] ?? $value['value'] ?? null) : null;
    }
}
