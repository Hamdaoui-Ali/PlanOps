<?php

use App\Domain\Collaboration\Actions\RemoveProjectMember;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Jobs\DeliverNotificationOutcome;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Tasks\Models\Task;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('uses bounded retry settings and records sanitized failure metadata', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $job = new DeliverNotificationOutcome(NotificationOutcome::invitationCreated(8, $project->id, $user->id, 'Launch'));
    $exception = new RuntimeException('raw invitation token should not be persisted: secret-token');

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 60]);

    $job->failed($exception);

    expect(NotificationDeliveryFailure::query()->sole()->only(['event_type', 'recipient_id', 'project_id', 'exception_class']))
        ->toBe([
            'event_type' => 'INVITATION_CREATED',
            'recipient_id' => $user->id,
            'project_id' => $project->id,
            'exception_class' => RuntimeException::class,
        ])
        ->and(NotificationDeliveryFailure::query()->sole()->message)->not->toContain('secret-token');
});

it('removes a revoked invitation target before persisting a delayed notification', function (): void {
    $recipient = User::factory()->create();
    $project = Project::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'project_id' => $project->id,
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'revoked_at' => now(),
    ]);
    $outcome = NotificationOutcome::invitationCreated($invitation->id, $project->id, $recipient->id, $project->name);

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBeNull();
});

it('suppresses assignment notification delivery when the recipient is no longer active', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $recipient->id, 'removed_at' => now()]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $recipient->id]);
    $outcome = NotificationOutcome::assigneeChanged($task->id, $project->id, $recipient->id, null, $recipient->id, $owner->id, $task->title);

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('suppresses delayed assignment delivery when the recipient is no longer assigned', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $replacement = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $recipient->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $replacement->id]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $replacement->id]);
    $outcome = NotificationOutcome::assigneeChanged($task->id, $project->id, $recipient->id, null, $recipient->id, $owner->id, $task->title);

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBeNull();
    Notification::assertNothingSent();
});

it('suppresses notification delivery for a deactivated recipient', function (): void {
    Notification::fake();
    $recipient = User::factory()->create();
    $recipient->forceFill(['deactivated_at' => now()])->save();
    $project = Project::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'project_id' => $project->id,
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'accepted_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->addDay(),
    ]);
    $outcome = NotificationOutcome::invitationCreated($invitation->id, $project->id, $recipient->id, $project->name);

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('redacts an existing invitation target when the recipient deactivates before retry', function (): void {
    Notification::fake();
    $recipient = User::factory()->create();
    $project = Project::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'project_id' => $project->id,
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'accepted_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->addDay(),
    ]);
    $outcome = NotificationOutcome::invitationCreated($invitation->id, $project->id, $recipient->id, $project->name);
    $job = new DeliverNotificationOutcome($outcome);

    $job->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);
    expect(PlanOpsNotification::query()->sole()->target_id)->toBe($invitation->id);

    $recipient->forceFill(['deactivated_at' => now()])->save();
    Notification::fake();
    $job->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBeNull();
    Notification::assertNothingSent();
});

it('redacts an existing assignment target when the recipient is removed before retry', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $recipient->id]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $recipient->id]);
    $outcome = NotificationOutcome::assigneeChanged($task->id, $project->id, $recipient->id, null, $recipient->id, $owner->id, $task->title);
    $job = new DeliverNotificationOutcome($outcome);

    $job->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);
    expect(PlanOpsNotification::query()->sole()->target_id)->toBe($task->id);

    (new RemoveProjectMember)->handle($owner, $project, $recipient);
    Notification::fake();
    $job->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBeNull();
    Notification::assertNothingSent();
});

it('suppresses duplicate queued mail for one idempotency key', function (): void {
    Notification::fake();
    $recipient = User::factory()->create();
    $project = Project::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'project_id' => $project->id,
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'accepted_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->addDay(),
    ]);
    $outcome = NotificationOutcome::invitationCreated($invitation->id, $project->id, $recipient->id, $project->name);
    $persist = new \App\Domain\Notifications\Actions\PersistNotificationOutcome;
    $job = new DeliverNotificationOutcome($outcome);

    $job->handle($persist);
    $job->handle($persist);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBe($invitation->id);
    Notification::assertSentToTimes($recipient, \App\Notifications\PlanOpsNotificationMail::class, 1);
});

it('keeps the persisted notification when mail delivery fails', function (): void {
    $recipient = User::factory()->create();
    $project = Project::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'project_id' => $project->id,
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'accepted_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->addDay(),
    ]);
    $outcome = NotificationOutcome::invitationCreated($invitation->id, $project->id, $recipient->id, $project->name);
    $exception = new RuntimeException('smtp unavailable');

    Mail::shouldReceive('mailer')->once()->andThrow($exception);

    expect(fn (): mixed => (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome))
        ->toThrow($exception);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBe($invitation->id);
});

it('delivers a mail notification only for a still-authorized target', function (): void {
    Notification::fake();
    $recipient = User::factory()->create();
    $project = Project::factory()->create();
    $invitation = ProjectInvitation::factory()->create([
        'project_id' => $project->id,
        'email' => $recipient->email,
        'normalized_email' => strtolower($recipient->email),
        'accepted_at' => null,
        'revoked_at' => null,
        'expires_at' => now()->addDay(),
    ]);
    $outcome = NotificationOutcome::invitationCreated($invitation->id, $project->id, $recipient->id, $project->name);

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    Notification::assertSentTo($recipient, \App\Notifications\PlanOpsNotificationMail::class);
});

it('delivers an assignment notification to a legacy project owner without membership', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    $task = Task::factory()->forProject($project)->create([
        'user_id' => $owner->id,
        'assignee_id' => $owner->id,
    ]);
    $outcome = NotificationOutcome::assigneeChanged(
        $task->id,
        $project->id,
        $owner->id,
        null,
        $owner->id,
        $owner->id,
        $task->title,
    );

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBe($task->id);
    Notification::assertSentTo($owner, \App\Notifications\PlanOpsNotificationMail::class);
});
