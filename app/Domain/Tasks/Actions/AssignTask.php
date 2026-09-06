<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Jobs\DeliverNotificationOutcome;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AssignTask
{
    public function handle(User $actor, Task $task, ?User $assignee): Task
    {
        Gate::forUser($actor)->authorize('assign', $task);

        $updatedTask = DB::transaction(function () use ($actor, $task, $assignee): Task {
            $lockedTask = Task::query()->accessibleBy($actor)->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $membership = $assignee === null ? null : ProjectMembership::query()
                ->where('project_id', $lockedTask->project_id)
                ->where('user_id', $assignee->getKey())
                ->whereNull('removed_at')
                ->lockForUpdate()
                ->first();

            if ($assignee !== null && $membership === null) {
                throw ValidationException::withMessages(['assignee_id' => 'Choose an active member of this project.']);
            }

            $oldAssigneeId = $lockedTask->assignee_id;
            $newAssigneeId = $assignee?->getKey();
            if ((string) $oldAssigneeId === (string) $newAssigneeId) {
                return $lockedTask;
            }

            $lockedTask->forceFill(['assignee_id' => $newAssigneeId])->save();
            app(TaskActivityRecorder::class)->record(
                $lockedTask,
                TaskActivityType::ASSIGNEE_CHANGED,
                'assignee_id',
                $oldAssigneeId,
                $newAssigneeId,
                ['old_assignee_id' => $oldAssigneeId, 'new_assignee_id' => $newAssigneeId],
                $actor,
            );

            return $lockedTask->refresh();
        });

        if ($assignee !== null && (string) $updatedTask->assignee_id !== (string) $task->assignee_id) {
            $outcome = NotificationOutcome::assigneeChanged(
                $updatedTask->getKey(),
                $updatedTask->project_id,
                $assignee->getKey(),
                $task->assignee_id,
                $assignee->getKey(),
                $actor->getKey(),
                $updatedTask->title,
            );
            $dispatch = fn (): mixed => DeliverNotificationOutcome::dispatch($outcome);
            if (DB::transactionLevel() > 0) {
                DB::afterCommit($dispatch);
            } else {
                $dispatch();
            }
        }

        return $updatedTask;
    }
}
