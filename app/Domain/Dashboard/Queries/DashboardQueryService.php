<?php

namespace App\Domain\Dashboard\Queries;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Dashboard\ValueObjects\DashboardSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class DashboardQueryService
{
    public function for(User $user, ReportPeriod $period): DashboardSnapshot
    {
        $statusCounts = array_fill_keys(array_map(
            fn (TaskStatus $status): string => $status->value,
            [TaskStatus::BACKLOG, TaskStatus::NOT_STARTED, TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW, TaskStatus::BLOCKED, TaskStatus::DONE],
        ), 0);

        $currentTasks = Task::query()
            ->ownedBy($user)
            ->whereNull('parent_task_id')
            ->whereIn('status', array_keys($statusCounts))
            ->get(['id', 'status', 'due_on']);

        foreach ($currentTasks as $task) {
            $statusCounts[$task->status->value]++;
        }

        $today = CarbonImmutable::now($user->preference?->timezone ?? 'Africa/Casablanca')->startOfDay();
        $overdueCount = $currentTasks->filter(fn (Task $task): bool => $task->due_on !== null && $task->due_on->toDateString() < $today->toDateString() && ! in_array($task->status, [TaskStatus::DONE, TaskStatus::CANCELLED], true))->count();

        $activities = TaskActivity::query()
            ->ownedBy($user)
            ->where('created_at', '>=', $period->start)
            ->where('created_at', '<', $period->end)
            ->with(['task' => fn ($tasks) => $tasks->withTrashed()->with('project'), 'project'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $validActivities = $activities->filter(fn (TaskActivity $activity): bool => $activity->task !== null && ! $activity->task->trashed());
        $created = $validActivities
            ->where('event_type', TaskActivityType::TASK_CREATED)
            ->filter(fn (TaskActivity $activity): bool => $activity->task->parent_task_id === null)
            ->pluck('task_id')->unique()->count();
        $completed = $validActivities
            ->where('event_type', TaskActivityType::STATUS_CHANGED)
            ->filter(fn (TaskActivity $activity): bool => $activity->task->parent_task_id === null && $this->newStatus($activity) === TaskStatus::DONE->value)
            ->pluck('task_id')->unique()->count();

        return new DashboardSnapshot(
            reportPeriod: $period,
            activeProjects: Project::query()->ownedBy($user)->whereNull('archived_at')->whereNotIn('status', [ProjectStatus::COMPLETED->value, ProjectStatus::CANCELLED->value])->count(),
            statusCounts: $statusCounts,
            overdueCount: $overdueCount,
            period: ['created' => $created, 'completed' => $completed, 'balance' => $created - $completed],
            recentActivity: new Collection($validActivities->take(8)->values()->all()),
        );
    }

    private function newStatus(TaskActivity $activity): ?string
    {
        $value = $activity->new_value;

        return is_array($value) ? ($value['status'] ?? $value['value'] ?? null) : null;
    }
}
