<?php

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Jobs\DeliverNotificationOutcome;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
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
