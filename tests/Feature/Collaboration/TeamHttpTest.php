<?php

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Models\User;

it('renders the project team for an active member', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('projects.team', $project));

    $response->assertOk()->assertSee($project->name)->assertSee($owner->email);
});

it('accepts a matching invitation through the HTTP endpoint', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    $invitation = app(\App\Domain\Collaboration\Actions\InviteProjectMember::class)
        ->handle($owner, $project, $invitee->email, ProjectRole::MEMBER);

    $response = $this->actingAs($invitee)->post(route('invitations.accept', $invitation->plain_token));

    $response->assertRedirect(route('projects.index'));
    expect($project->memberships()->where('user_id', $invitee->id)->where('role', ProjectRole::MEMBER->value)->exists())->toBeTrue();
});
