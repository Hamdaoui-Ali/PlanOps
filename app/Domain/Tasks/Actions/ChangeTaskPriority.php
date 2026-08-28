<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use ValueError;

class ChangeTaskPriority
{
    public function handle(User $user, Task $task, TaskPriority|string $priority): Task
    {
        $priority = $this->priority($priority);

        Gate::forUser($user)->authorize('changePriority', $task);

        $updatedTask = DB::transaction(function () use ($user, $task, $priority): Task {
            $ownedTask = Task::query()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($ownedTask->priority === $priority) {
                return $ownedTask;
            }

            $oldPriority = $ownedTask->priority;
            $ownedTask->forceFill(['priority' => $priority])->save();

            app(TaskActivityRecorder::class)->record(
                $ownedTask,
                TaskActivityType::PRIORITY_CHANGED,
                'priority',
                $oldPriority,
                $priority,
            );

            return $ownedTask;
        });

        return Task::query()
            ->ownedBy($user)
            ->whereKey($updatedTask->getKey())
            ->firstOrFail();
    }

    private function priority(TaskPriority|string $priority): TaskPriority
    {
        if ($priority instanceof TaskPriority) {
            return $priority;
        }

        try {
            return TaskPriority::from($priority);
        } catch (ValueError) {
            throw ValidationException::withMessages([
                'priority' => 'Choose a valid task priority.',
            ]);
        }
    }
}
