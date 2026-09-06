<?php

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Domain\Notifications\Actions\PersistNotificationOutcome;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists one recipient-scoped row per idempotency key', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $outcome = NotificationOutcome::invitationCreated(8, $project->id, $user->id, 'Launch');

    $first = (new PersistNotificationOutcome)->handle($outcome);
    $second = (new PersistNotificationOutcome)->handle($outcome);

    expect($first->id)->toBe($second->id)
        ->and(PlanOpsNotification::query()->where('recipient_id', $user->id)->count())->toBe(1)
        ->and($first->data)->not->toHaveKey('token');
});
