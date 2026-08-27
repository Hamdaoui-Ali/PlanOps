<?php

namespace Database\Factories;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskActivity>
 */
class TaskActivityFactory extends Factory
{
    protected $model = TaskActivity::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => fn (array $attributes): int => Task::query()->withTrashed()->findOrFail($attributes['task_id'])->user_id,
            'project_id' => fn (array $attributes): int => Task::query()->withTrashed()->findOrFail($attributes['task_id'])->project_id,
            'event_type' => TaskActivityType::TASK_UPDATED,
            'field' => null,
            'old_value' => null,
            'new_value' => null,
            'metadata' => null,
            'created_at' => now(),
        ];
    }

    public function forTask(Task $task): static
    {
        return $this->state(fn (): array => ['task_id' => $task->id, 'user_id' => $task->user_id, 'project_id' => $task->project_id]);
    }

    public function statusChanged(): static
    {
        return $this->state(fn (): array => ['event_type' => TaskActivityType::STATUS_CHANGED]);
    }
}
