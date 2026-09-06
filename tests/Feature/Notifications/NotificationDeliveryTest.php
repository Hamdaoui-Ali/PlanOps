<?php

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

it('removes a task target when the recipient is no longer an active member', function (): void {
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $recipient->id, 'removed_at' => now()]);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => $recipient->id]);
    $outcome = NotificationOutcome::assigneeChanged($task->id, $project->id, $recipient->id, null, $recipient->id, $owner->id, $task->title);

    (new DeliverNotificationOutcome($outcome))->handle(new \App\Domain\Notifications\Actions\PersistNotificationOutcome);

    expect(PlanOpsNotification::query()->sole()->target_id)->toBeNull();
});
