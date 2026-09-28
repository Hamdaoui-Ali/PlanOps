<?php

use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
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
    $admin = User::factory()->create(['name' => 'Admin User']);
    $project = teamWorkHttpProject($owner);
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
    Task::factory()->forProject($project)->active()->create(['assignee_id' => $admin->id]);
    Task::factory()->forProject($project)->blocked()->create(['assignee_id' => $owner->id, 'due_on' => now()->subDay()]);
    Task::factory()->forProject($project)->done()->create(['assignee_id' => $admin->id, 'completed_at' => now()->subDay()]);
    Task::factory()->forProject($project)->create(['status' => TaskStatus::NOT_STARTED]);

    $this->actingAs($owner)->get("/projects/{$project->id}/team/work")
        ->assertOk()
        ->assertSee('Team Work')
        ->assertSee('Workload overview')
        ->assertSee('Active work')
        ->assertSee('Blocked')
        ->assertSee('Overdue')
        ->assertSee('Completed this week')
        ->assertSee('Unassigned work')
        ->assertSee('Admin User')
        ->assertSee('Owner')
        ->assertSee('team-work-table', false)
        ->assertDontSee('productivity');

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

test('manager links to Team Work from the project and Team surfaces', function (): void {
    $owner = User::factory()->create();
    $project = teamWorkHttpProject($owner);
    $teamWorkUrl = route('projects.team.work', $project, absolute: false);

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee($teamWorkUrl, false)
        ->assertSee('Team Work');

    $this->actingAs($owner)->get(route('projects.team', $project))
        ->assertOk()
        ->assertSee($teamWorkUrl, false)
        ->assertSee('Team Work');
});

test('an empty Team Work page explains that there is no active workload yet', function (): void {
    $owner = User::factory()->create();
    $project = teamWorkHttpProject($owner);

    $this->actingAs($owner)->get(route('projects.team.work', $project))
        ->assertOk()
        ->assertSee('No active workload yet.')
        ->assertSee('Workload by member', false);
});
