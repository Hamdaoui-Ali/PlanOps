<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteTask
{
    public function handle(User $user, Task $task): Task
    {
        Gate::forUser($user)->authorize('delete', $task);

        return DB::transaction(function () use ($user, $task): Task {
            $ownedTask = Task::query()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->first();

            if ($ownedTask === null) {
                return $task;
            }

            $ownedTask->delete();

            app(TaskActivityRecorder::class)->record(
                $ownedTask,
                TaskActivityType::TASK_DELETED,
                null,
                null,
                null,
            );

            return $ownedTask;
        });
    }
}
