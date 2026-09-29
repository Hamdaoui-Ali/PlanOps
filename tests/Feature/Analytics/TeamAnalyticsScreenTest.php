<?php

use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function teamAnalyticsHttpProject(User $owner): Project
{
    $project = Project::factory()->active()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
    ]);
    ProjectMembership::factory()->owner()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
    ]);

    return $project;
}

test('owners and admins can open project team analytics with a selected period', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $project = teamAnalyticsHttpProject($owner);
    ProjectMembership::factory()->admin()->create([
        'project_id' => $project->id,
        'user_id' => $admin->id,
    ]);

    foreach ([$owner, $admin] as $viewer) {
        $this->actingAs($viewer)->get(route('projects.team.analytics', ['project' => $project, 'period' => 'month']))
            ->assertOk()
            ->assertSee('action="'.route('projects.team.analytics', $project).'"', false)
            ->assertSee('Team Analytics')
            ->assertSee($project->name)
            ->assertSee('Month');
    }
});

test('members and removed members cannot open project team analytics', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $removed = User::factory()->create();
    $project = teamAnalyticsHttpProject($owner);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    ProjectMembership::factory()->create([
        'project_id' => $project->id,
        'user_id' => $removed->id,
        'removed_at' => now(),
    ]);

    $this->actingAs($member)->get(route('projects.team.analytics', $project))->assertForbidden();
    $this->actingAs($removed)->get(route('projects.team.analytics', $project))->assertNotFound();
});

test('a foreign project is not resolved through the project team analytics route', function (): void {
    $owner = User::factory()->create();
    $foreignOwner = User::factory()->create();
    $project = teamAnalyticsHttpProject($owner);
    $foreignProject = teamAnalyticsHttpProject($foreignOwner);

    $this->actingAs($owner)
        ->get(route('projects.team.analytics', $foreignProject))
        ->assertNotFound();

    expect($project->id)->not->toBe($foreignProject->id);
});
