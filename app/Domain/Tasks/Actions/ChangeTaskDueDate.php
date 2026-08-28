<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeTaskDueDate
{
    public function handle(User $user, Task $task, CarbonImmutable|string|null $dueOn): Task
    {
        $dueOn = $this->dueOn($dueOn);

        Gate::forUser($user)->authorize('changeDueDate', $task);

        $updatedTask = DB::transaction(function () use ($user, $task, $dueOn): Task {
            $ownedTask = Task::query()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldDueOn = $ownedTask->due_on?->toDateString();
            $newDueOn = $dueOn?->toDateString();

            if ($oldDueOn === $newDueOn) {
                return $ownedTask;
            }

            $ownedTask->forceFill(['due_on' => $newDueOn])->save();

            app(TaskActivityRecorder::class)->record(
                $ownedTask,
                TaskActivityType::DUE_DATE_CHANGED,
                'due_on',
                $oldDueOn,
                $newDueOn,
            );

            return $ownedTask;
        });

        return Task::query()
            ->ownedBy($user)
            ->whereKey($updatedTask->getKey())
            ->firstOrFail();
    }

    private function dueOn(CarbonImmutable|string|null $dueOn): ?CarbonImmutable
    {
        if ($dueOn === null) {
            return null;
        }

        if ($dueOn instanceof CarbonImmutable) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $dueOn->toDateString());
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $dueOn);
            $errors = CarbonImmutable::getLastErrors();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'due_on' => 'Enter a valid due date.',
            ]);
        }

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw ValidationException::withMessages([
                'due_on' => 'Enter a valid due date.',
            ]);
        }

        return $date;
    }
}
