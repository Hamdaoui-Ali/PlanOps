<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Collaboration\Models\ProjectMembership;
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

        return DB::transaction(function () use ($actor, $task, $assignee): Task {
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
    }
}
