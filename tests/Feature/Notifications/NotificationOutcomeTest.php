<?php

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Enums\NotificationEventType;

it('builds redacted stable invitation and assignment outcomes', function (): void {
    $invitation = NotificationOutcome::invitationCreated(8, 3, 21, 'Launch');
    $assignment = NotificationOutcome::assigneeChanged(55, 3, 21, null, 21, 9, 'Prepare brief');

    expect($invitation->eventType)->toBe(NotificationEventType::INVITATION_CREATED)
        ->and($invitation->recipientId)->toBe(21)
        ->and($invitation->idempotencyKey())->toBe('INVITATION_CREATED:8:21')
        ->and($invitation->toPayload())->not->toHaveKey('token')
        ->and($assignment->eventType)->toBe(NotificationEventType::ASSIGNEE_CHANGED)
        ->and($assignment->idempotencyKey())->toBe('ASSIGNEE_CHANGED:55:21')
        ->and($assignment->toPayload()['task_title'])->toBe('Prepare brief');
});
