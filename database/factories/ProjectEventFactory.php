<?php

namespace Database\Factories;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectEvent> */
class ProjectEventFactory extends Factory
{
    protected $model = ProjectEvent::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'actor_user_id' => User::factory(),
            'subject_user_id' => null,
            'event_type' => ProjectEventType::INVITATION_CREATED,
            'metadata' => ['source' => 'factory'],
            'created_at' => now(),
        ];
    }
}
