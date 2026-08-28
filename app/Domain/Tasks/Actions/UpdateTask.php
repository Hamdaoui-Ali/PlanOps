<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateTask
{
    public function handle(User $user, Task $task, array $attributes): Task
    {
        $values = [
            'title' => $this->trimmed($attributes['title'] ?? ''),
        ];

        if (array_key_exists('description', $attributes)) {
            $values['description'] = $this->nullableTrimmed($attributes['description']);
        }

        Validator::make($values, [
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
        ])->validate();

        Gate::forUser($user)->authorize('update', $task);

        $updatedTask = DB::transaction(function () use ($user, $task, $values): Task {
            $ownedTask = Task::query()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $changes = [];
            foreach ($values as $field => $value) {
                if ($ownedTask->getAttribute($field) !== $value) {
                    $changes[$field] = $value;
                }
            }

            if ($changes === []) {
                return $ownedTask;
            }

            $oldValues = [];
            foreach (array_keys($changes) as $field) {
                $oldValues[$field] = $ownedTask->getAttribute($field);
            }

            $ownedTask->forceFill($changes)->save();

            foreach ($changes as $field => $value) {
                app(TaskActivityRecorder::class)->record(
                    $ownedTask,
                    TaskActivityType::TASK_UPDATED,
                    $field,
                    $oldValues[$field],
                    $value,
                );
            }

            return $ownedTask;
        });

        return Task::query()
            ->ownedBy($user)
            ->whereKey($updatedTask->getKey())
            ->firstOrFail();
    }

    private function trimmed(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function nullableTrimmed(mixed $value): mixed
    {
        $value = $this->trimmed($value);

        return $value === '' ? null : $value;
    }
}
