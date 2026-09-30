<?php

use App\Domain\Collaboration\Actions\InviteProjectMember;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectInvitation;
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

    $invitation = app(InviteProjectMember::class)
        ->handle($owner, $project, $invitee->email, ProjectRole::MEMBER);

    $response = $this->actingAs($invitee)->post(route('invitations.accept', $invitation->plain_token));

    $response->assertRedirect(route('projects.index'));
    expect($project->memberships()->where('user_id', $invitee->id)->where('role', ProjectRole::MEMBER->value)->exists())->toBeTrue();
});

it('keeps the public invitation preview generic', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
        'name' => 'Confidential Launch Plan',
    ]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $invitation = (new InviteProjectMember)->handle($owner, $project, 'preview@example.com', ProjectRole::MEMBER);

    $this->get(route('invitations.show', $invitation->plain_token))
        ->assertOk()
        ->assertSee('You have a project invitation.')
        ->assertSee('Sign in with the invited email address to accept.')
        ->assertDontSee($project->name)
        ->assertDontSee('preview@example.com');
});

it('rate limits repeated public invitation previews', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $invitation = (new InviteProjectMember)->handle($owner, $project, 'rate@example.com', ProjectRole::MEMBER);
    $uri = route('invitations.show', $invitation->plain_token, absolute: false);

    foreach (range(1, 6) as $_) {
        $this->get($uri)->assertOk();
    }

    $this->get($uri)->assertTooManyRequests();
});

it('shows pending invitations on the project team surface', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    (new InviteProjectMember)->handle($owner, $project, 'pending@example.com', ProjectRole::MEMBER);

    $this->actingAs($owner)->get(route('projects.team', $project))
        ->assertOk()
        ->assertSee('Pending invitations')
        ->assertSee('pending-invitations-panel', false)
        ->assertSee('pending@example.com')
        ->assertSee('Awaiting acceptance')
        ->assertSee('Pending');
});

it('does not render expired invitations as pending', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $invitation = (new InviteProjectMember)->handle($owner, $project, 'expired@example.com', ProjectRole::MEMBER);
    ProjectInvitation::query()->whereKey($invitation->getKey())->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($owner)->get(route('projects.team', $project))
        ->assertOk()
        ->assertDontSee('Pending invitations')
        ->assertDontSee('expired@example.com');
});

it('allows a project manager to cancel a pending invitation', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    $invitation = (new InviteProjectMember)->handle($owner, $project, 'cancel@example.com', ProjectRole::MEMBER);

    $this->actingAs($owner)->get(route('projects.team', $project))
        ->assertOk()
        ->assertSee('Cancel')
        ->assertSee(route('invitations.revoke', $invitation, absolute: false), false);

    $this->actingAs($owner)->delete(route('invitations.revoke', $invitation))
        ->assertRedirect();

    expect(ProjectInvitation::findOrFail($invitation->id)->revoked_at)->not->toBeNull();
});
