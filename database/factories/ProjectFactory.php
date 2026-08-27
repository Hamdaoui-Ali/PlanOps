<?php

namespace Database\Factories;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'key' => strtoupper(fake()->bothify('??###')),
            'description' => fake()->optional()->sentence(),
            'status' => ProjectStatus::PLANNED,
            'color' => fake()->optional()->hexColor(),
            'icon' => null,
            'start_on' => null,
            'target_on' => null,
            'next_task_number' => 1,
            'archived_at' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::PLANNED]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::ACTIVE]);
    }

    public function onHold(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::ON_HOLD]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::COMPLETED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => ProjectStatus::CANCELLED]);
    }
}
