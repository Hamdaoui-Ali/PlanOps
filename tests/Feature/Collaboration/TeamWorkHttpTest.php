<?php

use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function teamWorkHttpProject(User $owner): Project
{
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    return $project;
}

test('owners and admins can open the Team Work route', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = teamWorkHttpProject($owner);
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);

    $this->actingAs($owner)->get("/projects/{$project->id}/team/work")
        ->assertOk()
        ->assertSee('Team Work');

    $this->actingAs($admin)->get("/projects/{$project->id}/team/work")
        ->assertOk()
        ->assertSee('Team Work');
});

test('members and removed members cannot open Team Work', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $removed = User::factory()->create();
    $project = teamWorkHttpProject($owner);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    ProjectMembership::factory()->create([
        'project_id' => $project->id,
        'user_id' => $removed->id,
        'removed_at' => now(),
    ]);

    $this->actingAs($member)->get("/projects/{$project->id}/team/work")->assertForbidden();
    $this->actingAs($removed)->get("/projects/{$project->id}/team/work")->assertNotFound();
});

test('a foreign project is not resolved through the Team Work route', function (): void {
    $owner = User::factory()->create();
    $foreignOwner = User::factory()->create();
    $project = teamWorkHttpProject($owner);
    $foreignProject = teamWorkHttpProject($foreignOwner);

    $this->actingAs($owner)
        ->get("/projects/{$foreignProject->id}/team/work")
        ->assertNotFound();

    expect($project->id)->not->toBe($foreignProject->id);
});
