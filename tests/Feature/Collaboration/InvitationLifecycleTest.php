<?php

use App\Domain\Collaboration\Actions\AcceptProjectInvitation;
use App\Domain\Collaboration\Actions\InviteProjectMember;
use App\Domain\Collaboration\Actions\ResendProjectInvitation;
use App\Domain\Collaboration\Actions\RevokeProjectInvitation;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function invitationProject(User $owner): Project
{
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    return $project;
}

it('normalizes invite emails and prevents duplicate pending invitations', function (): void {
    $owner = User::factory()->create();
    $project = invitationProject($owner);
    $invite = (new InviteProjectMember)->handle($owner, $project, '  NEW@Example.com ', ProjectRole::MEMBER);

    expect($invite->normalized_email)->toBe('new@example.com')
        ->and($invite->token_hash)->not->toBeEmpty()
        ->and($invite->plain_token)->not->toBeEmpty()
        ->and($invite->plain_token)->not->toContain('new@example.com')
        ->and(fn () => (new InviteProjectMember)->handle($owner, $project, 'new@example.com', ProjectRole::MEMBER))
        ->toThrow(ValidationException::class);
});

it('allows admins to invite members but not admins', function (): void {
    $owner = User::factory()->create();
    $project = invitationProject($owner);
    $admin = User::factory()->create();
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);

    expect(fn () => (new InviteProjectMember)->handle($admin, $project, 'member@example.com', ProjectRole::MEMBER))
        ->not->toThrow(Throwable::class)
        ->and(fn () => (new InviteProjectMember)->handle($admin, $project, 'admin@example.com', ProjectRole::ADMIN))
        ->toThrow(AuthorizationException::class);
});

it('accepts an invitation once and reactivates an existing membership', function (): void {
    $owner = User::factory()->create();
    $project = invitationProject($owner);
    $member = User::factory()->create(['email' => 'member@example.com']);
    $invitation = (new InviteProjectMember)->handle($owner, $project, $member->email, ProjectRole::MEMBER);

    $membership = (new AcceptProjectInvitation)->handle($member, $invitation->plain_token);

    expect($membership->user_id)->toBe($member->id)
        ->and($membership->project_id)->toBe($project->id)
        ->and(ProjectInvitation::find($invitation->id)->accepted_at)->not->toBeNull()
        ->and(fn () => (new AcceptProjectInvitation)->handle($member, $invitation->plain_token))
        ->toThrow(ValidationException::class);
});

it('preserves an admin invitation role and keeps the creator fully operational', function (): void {
    $owner = User::factory()->create();
    $project = invitationProject($owner);
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $invitation = (new InviteProjectMember)->handle($owner, $project, $admin->email, ProjectRole::ADMIN);
    $task = Task::factory()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    (new AcceptProjectInvitation)->handle($admin, $invitation->plain_token);

    expect(ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $admin->id)->value('role'))
        ->toBe(ProjectRole::ADMIN)
        ->and($admin->can('update', $task))->toBeTrue()
        ->and($admin->can('assign', $task))->toBeTrue()
        ->and($owner->can('update', $task))->toBeTrue()
        ->and($owner->can('delete', $task))->toBeTrue();
});

it('revokes and resends invitations by rotating the token', function (): void {
    $owner = User::factory()->create();
    $project = invitationProject($owner);
    $invitation = (new InviteProjectMember)->handle($owner, $project, 'member@example.com', ProjectRole::MEMBER);
    $oldToken = $invitation->plain_token;

    (new RevokeProjectInvitation)->handle($owner, $invitation);
    expect(ProjectInvitation::find($invitation->id)->revoked_at)->not->toBeNull();

    $replacement = (new InviteProjectMember)->handle($owner, $project, 'member@example.com', ProjectRole::MEMBER);
    $resent = (new ResendProjectInvitation)->handle($owner, $replacement);

    expect($resent->plain_token)->not->toBe($oldToken)
        ->and($resent->token_hash)->not->toBe($replacement->token_hash);
});
