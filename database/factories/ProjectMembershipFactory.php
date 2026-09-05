<?php

namespace Database\Factories;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectMembership> */
class ProjectMembershipFactory extends Factory
{
    protected $model = ProjectMembership::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'role' => ProjectRole::MEMBER,
            'joined_at' => now(),
            'removed_at' => null,
            'removed_by_user_id' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => ProjectRole::OWNER]);
    }

    public function admin(): static
    {
        return $this->state(['role' => ProjectRole::ADMIN]);
    }
}
