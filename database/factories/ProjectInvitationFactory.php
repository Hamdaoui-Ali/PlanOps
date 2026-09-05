<?php

namespace Database\Factories;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectInvitation> */
class ProjectInvitationFactory extends Factory
{
    protected $model = ProjectInvitation::class;

    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'project_id' => Project::factory(),
            'email' => $email,
            'normalized_email' => strtolower(trim($email)),
            'role' => ProjectRole::MEMBER,
            'invited_by_user_id' => User::factory(),
            'token_hash' => hash('sha256', fake()->unique()->uuid()),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
            'last_sent_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => ProjectRole::ADMIN]);
    }
}
