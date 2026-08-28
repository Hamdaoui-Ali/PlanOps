<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

class RestoreTask
{
    public function handle(User $user, Task $task): Task
    {
        Gate::forUser($user)->authorize('restore', $task);

        if (! $task->trashed()) {
            throw new LogicException('Only a trashed task can be restored.');
        }

        return DB::transaction(function () use ($user, $task): Task {
            $ownedTask = Task::query()
                ->withTrashed()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $ownedTask->trashed()) {
                throw new LogicException('Only a trashed task can be restored.');
            }

            $ownedTask->restore();

            app(TaskActivityRecorder::class)->record(
                $ownedTask,
                TaskActivityType::TASK_RESTORED,
                null,
                null,
                null,
            );

            return $ownedTask;
        });
    }
}
