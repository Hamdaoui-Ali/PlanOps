<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateTaskDetails
{
    public function handle(User $user, Task $task, array $attributes): Task
    {
        $values = [
            'title' => $this->trimmed($attributes['title'] ?? ''),
            'description' => $this->nullableTrimmed($attributes['description'] ?? null),
            'status' => $this->statusValue($attributes['status'] ?? null),
            'priority' => $this->priorityValue($attributes['priority'] ?? null),
            'due_on' => $this->nullableTrimmed($attributes['due_on'] ?? null),
        ];

        Validator::make($values, [
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'priority' => ['required', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        $values['status'] = TaskStatus::from($values['status']);
        $values['priority'] = TaskPriority::from($values['priority']);

        Gate::forUser($user)->authorize('update', $task);

        $updatedTask = DB::transaction(function () use ($user, $task, $values): Task {
            $ownedTask = Task::query()
                ->ownedBy($user)
                ->whereKey($task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $changes = [];
            foreach ($values as $field => $value) {
                $current = $field === 'due_on'
                    ? $ownedTask->due_on?->toDateString()
                    : $ownedTask->getAttribute($field);

                if ($current !== $value) {
                    $changes[$field] = $value;
                }
            }

            if (array_key_exists('status', $changes)) {
                $previousStatus = $ownedTask->status;
                $nextStatus = $changes['status'];
                $changes['status_changed_at'] = now();

                if ($nextStatus === TaskStatus::IN_PROGRESS && $ownedTask->first_started_at === null) {
                    $changes['first_started_at'] = now();
                }

                if ($nextStatus === TaskStatus::DONE) {
                    $changes['completed_at'] = now();
                    $changes['cancelled_at'] = null;
                } elseif ($previousStatus === TaskStatus::DONE) {
                    $changes['completed_at'] = null;
                }

                if ($nextStatus === TaskStatus::CANCELLED) {
                    $changes['cancelled_at'] = now();
                    $changes['completed_at'] = null;
                } elseif ($previousStatus === TaskStatus::CANCELLED) {
                    $changes['cancelled_at'] = null;
                }
            }

            if ($changes === []) {
                return $ownedTask;
            }

            $oldValues = [];
            foreach (array_keys($changes) as $field) {
                if (in_array($field, ['status_changed_at', 'first_started_at', 'completed_at', 'cancelled_at'], true)) {
                    continue;
                }

                $oldValues[$field] = $field === 'due_on'
                    ? $ownedTask->due_on?->toDateString()
                    : $ownedTask->getAttribute($field);
            }

            $ownedTask->forceFill($changes)->save();

            foreach (array_keys($oldValues) as $field) {
                $type = match ($field) {
                    'status' => TaskActivityType::STATUS_CHANGED,
                    'priority' => TaskActivityType::PRIORITY_CHANGED,
                    'due_on' => TaskActivityType::DUE_DATE_CHANGED,
                    default => TaskActivityType::TASK_UPDATED,
                };

                $newValue = $field === 'due_on'
                    ? $ownedTask->due_on?->toDateString()
                    : $ownedTask->getAttribute($field);

                app(TaskActivityRecorder::class)->record(
                    $ownedTask,
                    $type,
                    $field,
                    $oldValues[$field],
                    $newValue,
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

    private function statusValue(mixed $status): mixed
    {
        return $status instanceof TaskStatus ? $status->value : $this->trimmed($status);
    }

    private function priorityValue(mixed $priority): mixed
    {
        return $priority instanceof TaskPriority ? $priority->value : $this->trimmed($priority);
    }
}
