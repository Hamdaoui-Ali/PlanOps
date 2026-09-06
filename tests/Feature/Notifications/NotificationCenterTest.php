<?php

use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists only the authenticated recipient notifications and supports read actions', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = PlanOpsNotification::query()->create([
        'recipient_id' => $user->id, 'event_type' => 'INVITATION_CREATED',
        'idempotency_key' => 'mine', 'data' => ['message' => 'Mine'],
    ]);
    PlanOpsNotification::query()->create([
        'recipient_id' => $other->id, 'event_type' => 'INVITATION_CREATED',
        'idempotency_key' => 'other', 'data' => ['message' => 'Other'],
    ]);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Mine')
        ->assertDontSee('Other')
        ->assertSee('Notifications');

    $this->actingAs($user)->patch(route('notifications.read', $mine))->assertRedirect();
    expect($mine->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('notifications.read-all'))->assertRedirect();
});

it('explains an invitation and lets the recipient accept it from the notification center', function (): void {
    $recipient = User::factory()->create(['name' => 'Invited Member']);
    $inviter = User::factory()->create(['name' => 'Project Owner']);
    $invitation = ProjectInvitation::factory()->create([
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'invited_by_user_id' => $inviter->id,
    ]);
    $notification = PlanOpsNotification::query()->create([
        'recipient_id' => $recipient->id,
        'event_type' => 'INVITATION_CREATED',
        'idempotency_key' => 'invitation-'.$invitation->id,
        'project_id' => $invitation->project_id,
        'target_type' => 'project_invitation',
        'target_id' => $invitation->id,
        'data' => ['project_id' => $invitation->project_id, 'project_name' => $invitation->project->name],
    ]);

    $this->actingAs($recipient)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Project Owner')
        ->assertSee($invitation->project->name)
        ->assertSee('Accept invitation')
        ->assertSee(route('notifications.accept-invitation', $notification));

    $this->actingAs($recipient)->post(route('notifications.accept-invitation', $notification))
        ->assertRedirect(route('projects.index'));

    expect($invitation->fresh()->accepted_at)->not->toBeNull()
        ->and(ProjectMembership::query()->where('project_id', $invitation->project_id)->where('user_id', $recipient->id)->exists())->toBeTrue();
});

it('lets the invited recipient decline a pending invitation', function (): void {
    $recipient = User::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
    ]);
    $notification = PlanOpsNotification::query()->create([
        'recipient_id' => $recipient->id,
        'event_type' => 'INVITATION_CREATED',
        'idempotency_key' => 'decline-'.$invitation->id,
        'project_id' => $invitation->project_id,
        'target_type' => 'project_invitation',
        'target_id' => $invitation->id,
        'data' => ['project_id' => $invitation->project_id, 'project_name' => $invitation->project->name],
    ]);

    $this->actingAs($recipient)->post(route('notifications.decline-invitation', $notification))
        ->assertRedirect(route('notifications.index'));

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});
