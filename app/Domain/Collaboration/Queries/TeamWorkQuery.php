<?php

namespace App\Domain\Collaboration\Queries;

use App\Domain\Collaboration\ValueObjects\TeamWorkMember;
use App\Domain\Collaboration\ValueObjects\TeamWorkSnapshot;
use App\Domain\Identity\Services\UserPeriodResolver;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;

final class TeamWorkQuery
{
    public function for(User $viewer, Project $project, ?CarbonImmutable $now = null): TeamWorkSnapshot
    {
        $project = Project::query()->accessibleBy($viewer)->whereKey($project->getKey())->firstOrFail();
        $period = (new UserPeriodResolver)->week($viewer, $now);
        $timezone = $viewer->preference?->timezone ?? 'Africa/Casablanca';
        $localToday = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $tasks = Task::query()
            ->accessibleBy($viewer)
            ->where('project_id', $project->getKey())
            ->whereNull('parent_task_id')
            ->get(['id', 'assignee_id', 'status', 'due_on', 'completed_at']);
        $memberships = $project->activeMemberships()->with('user:id,name,email')->orderBy('id')->get();

        $members = $memberships->map(function ($membership) use ($tasks, $localToday, $period): TeamWorkMember {
            $memberTasks = $tasks->where('assignee_id', $membership->user_id);
            $activeStatuses = [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW];
            $terminalStatuses = [TaskStatus::DONE, TaskStatus::CANCELLED];

            return new TeamWorkMember(
                userId: (int) $membership->user_id,
                name: $membership->user->name,
                email: $membership->user->email,
                role: $membership->role,
                activeCount: $memberTasks->whereIn('status', $activeStatuses)->count(),
                blockedCount: $memberTasks->where('status', TaskStatus::BLOCKED)->count(),
                overdueCount: $memberTasks->filter(fn (Task $task): bool => $task->isOverdueOn($localToday))->count(),
                completedThisWeekCount: $memberTasks->filter(function (Task $task) use ($period): bool {
                    return $task->status === TaskStatus::DONE
                        && $task->completed_at !== null
                        && $task->completed_at->greaterThanOrEqualTo($period->start)
                        && $task->completed_at->lessThan($period->end);
                })->count(),
            );
        })->values();
        $terminalStatuses = [TaskStatus::DONE, TaskStatus::CANCELLED];

        return new TeamWorkSnapshot(
            project: $project,
            members: $members,
            unassignedCount: $tasks->whereNull('assignee_id')->reject(fn (Task $task): bool => in_array($task->status, $terminalStatuses, true))->count(),
            weekStart: $period->start,
            weekEnd: $period->end,
        );
    }
}
