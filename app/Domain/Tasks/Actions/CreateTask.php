<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateTask
{
    public function handle(User $user, Project $project, array $attributes): Task
    {
        $values = [
            'title' => $this->trimmed($attributes['title'] ?? ''),
            'description' => $this->nullableTrimmed($attributes['description'] ?? null),
            'status' => $this->statusValue($attributes['status'] ?? TaskStatus::NOT_STARTED),
            'priority' => $this->priorityValue($attributes['priority'] ?? TaskPriority::MEDIUM),
            'due_on' => $this->nullableTrimmed($attributes['due_on'] ?? null),
            'parent_task_id' => $this->nullableTrimmed($attributes['parent_task_id'] ?? null),
        ];

        Validator::make($values, [
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'due_on' => ['nullable', 'date'],
            'parent_task_id' => ['nullable', 'integer'],
        ])->validate();

        $values['status'] = TaskStatus::from($values['status']);
        $values['priority'] = TaskPriority::from($values['priority']);

        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        return DB::transaction(function () use ($user, $project, $values): Task {
            $lockedProject = Project::query()
                ->whereKey($project->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $number = (int) $lockedProject->next_task_number;
            if ($number < 1) {
                throw new LogicException('Project task numbering must start at 1.');
            }

            $parent = null;
            $parentId = $values['parent_task_id'] ?? null;
            if ($parentId !== null) {
                $parent = Task::query()
                    ->whereKey($parentId)
                    ->where('user_id', $user->getKey())
                    ->where('project_id', $lockedProject->getKey())
                    ->whereNull('parent_task_id')
                    ->first();

                if ($parent === null) {
                    throw ValidationException::withMessages([
                        'parent_task_id' => 'The selected parent task is unavailable.',
                    ]);
                }
            }

            $task = Task::query()->create([
                ...$values,
                'user_id' => $user->getKey(),
                'project_id' => $lockedProject->getKey(),
                'number' => $number,
                'parent_task_id' => $parent?->getKey(),
                'status_changed_at' => now(),
            ]);

            $lockedProject->forceFill(['next_task_number' => $number + 1])->save();
            $task->setRelation('project', $lockedProject);
            $key = (new TaskKeyQuery)->displayKey($task);
            app(TaskActivityRecorder::class)->record(
                $task,
                TaskActivityType::TASK_CREATED,
                null,
                null,
                [
                    'display_key' => $key,
                    'status' => $task->status,
                    'priority' => $task->priority,
                ],
            );

            return $task;
        });
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
