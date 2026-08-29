<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChangeTaskStatus
{
    public function handle(User $user, Task $task, TaskStatus|string $status): Task
    {
        Gate::forUser($user)->authorize('update', $task);

        $value = $status instanceof TaskStatus ? $status->value : $status;
        Validator::make(['status' => $value], [
            'status' => ['required', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
        ])->validate();
        $nextStatus = TaskStatus::from($value);

        return DB::transaction(function () use ($user, $task, $nextStatus): Task {
            $ownedTask = Task::query()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $ownedTask->status;
            if ($previousStatus === $nextStatus) {
                return $ownedTask;
            }

            $now = now();
            $changes = [
                'status' => $nextStatus,
                'status_changed_at' => $now,
            ];

            if ($nextStatus === TaskStatus::IN_PROGRESS && $ownedTask->first_started_at === null) {
                $changes['first_started_at'] = $now;
            }

            if ($nextStatus === TaskStatus::DONE) {
                $changes['completed_at'] = $now;
                $changes['cancelled_at'] = null;
            } elseif ($previousStatus === TaskStatus::DONE) {
                $changes['completed_at'] = null;
            }

            if ($nextStatus === TaskStatus::CANCELLED) {
                $changes['cancelled_at'] = $now;
                $changes['completed_at'] = null;
            } elseif ($previousStatus === TaskStatus::CANCELLED) {
                $changes['cancelled_at'] = null;
            }

            $ownedTask->forceFill($changes)->save();
            app(TaskActivityRecorder::class)->record(
                $ownedTask,
                TaskActivityType::STATUS_CHANGED,
                'status',
                $previousStatus,
                $nextStatus,
            );

            return $ownedTask->refresh();
        });
    }
}
