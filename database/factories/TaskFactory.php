<?php

namespace Database\Factories;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes): int => Project::query()->findOrFail($attributes['project_id'])->user_id,
            'parent_task_id' => null,
            'number' => fake()->numberBetween(0, 1_000_000),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => TaskStatus::NOT_STARTED,
            'priority' => TaskPriority::MEDIUM,
            'due_on' => null,
            'position' => 0,
            'first_started_at' => null,
            'completed_at' => null,
            'cancelled_at' => null,
            'status_changed_at' => now(),
            'deleted_at' => null,
        ];
    }

    public function forProject(Project $project): static
    {
        return $this->state(fn (): array => ['project_id' => $project->id, 'user_id' => $project->user_id]);
    }

    public function withParent(Task $parent): static
    {
        return $this->state(fn (): array => [
            'parent_task_id' => $parent->id,
            'project_id' => $parent->project_id,
            'user_id' => $parent->user_id,
        ]);
    }

    public function backlog(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::BACKLOG]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::IN_PROGRESS, 'first_started_at' => now(), 'status_changed_at' => now()]);
    }

    public function inReview(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::IN_REVIEW, 'first_started_at' => now(), 'status_changed_at' => now()]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::BLOCKED, 'first_started_at' => now(), 'status_changed_at' => now()]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::DONE, 'first_started_at' => now()->subDay(), 'completed_at' => now(), 'cancelled_at' => null, 'status_changed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::CANCELLED, 'completed_at' => null, 'cancelled_at' => now(), 'status_changed_at' => now()]);
    }

    public function deleted(): static
    {
        return $this->state(fn (): array => ['deleted_at' => '2026-08-01 09:00:00+00:00']);
    }

    public function reopened(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TaskStatus::IN_PROGRESS,
            'first_started_at' => $attributes['first_started_at'] ?? '2026-08-20 09:00:00+00:00',
            'completed_at' => null,
            'cancelled_at' => null,
            'status_changed_at' => '2026-08-26 09:00:00+00:00',
        ]);
    }
}
