<?php

namespace App\Domain\Attention\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class AttentionQuery
{
    public function for(User $owner, Project $project): Collection
    {
        $now = CarbonImmutable::now();
        $today = $now->setTimezone($owner->preference?->timezone ?? 'Africa/Casablanca');

        return Task::query()->ownedBy($owner)->where('project_id', $project->getKey())->whereNull('parent_task_id')
            ->where(function ($query) use ($now, $today): void {
                $query->where(function ($query) use ($today): void {
                    $query->whereNotNull('due_on')->whereDate('due_on', '<', $today->toDateString())->whereNotIn('status', [TaskStatus::DONE->value, TaskStatus::CANCELLED->value]);
                })->orWhere('status', TaskStatus::BLOCKED->value)
                    ->orWhere(fn ($query) => $query->where('status', TaskStatus::IN_REVIEW->value)->where('status_changed_at', '<', $now->subDays(3)))
                    ->orWhere(fn ($query) => $query->whereIn('status', [TaskStatus::IN_PROGRESS->value, TaskStatus::IN_REVIEW->value, TaskStatus::BLOCKED->value])->where('updated_at', '<', $now->subDays(7)));
            })->with('project:id,key')->orderBy('updated_at')->get()->each(function (Task $task) use ($now, $today): void {
                $reasons = [];
                if ($task->isOverdueOn($today)) $reasons[] = 'Past due';
                if ($task->status === TaskStatus::BLOCKED) $reasons[] = 'Blocked work';
                if ($task->status === TaskStatus::IN_REVIEW && $task->status_changed_at?->lt($now->subDays(3))) $reasons[] = 'In review for '.$task->status_changed_at->diffInDays($now).' days';
                if (in_array($task->status, [TaskStatus::IN_PROGRESS, TaskStatus::IN_REVIEW, TaskStatus::BLOCKED], true) && $task->updated_at?->lt($now->subDays(7))) $reasons[] = 'No status change for '.$task->updated_at->diffInDays($now).' days';
                $task->setAttribute('attention_reasons', $reasons);
            });
    }
}
