<?php

namespace Database\Seeders;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

final class BrowserSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'name' => 'Browser Owner',
            'email' => 'browser-owner@example.test',
        ]);
        $admin = User::factory()->create([
            'name' => 'Browser Admin',
            'email' => 'browser-admin@example.test',
        ]);
        $member = User::factory()->create([
            'name' => 'Browser Member',
            'email' => 'browser-member@example.test',
        ]);
        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'owner_id' => $owner->id,
            'name' => 'Browser Collaboration',
            'key' => 'BROW',
            'status' => ProjectStatus::ACTIVE,
        ]);

        ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
        ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
        ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

        Task::factory()->forProject($project)->active()->create([
            'title' => 'Review launch checklist',
            'assignee_id' => $admin->id,
        ]);
        Task::factory()->forProject($project)->blocked()->create([
            'title' => 'Resolve release blocker',
            'assignee_id' => $owner->id,
            'due_on' => now()->subDay()->toDateString(),
        ]);
        Task::factory()->forProject($project)->create([
            'title' => 'Assign documentation owner',
            'status' => TaskStatus::NOT_STARTED,
        ]);
    }
}
