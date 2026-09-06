<?php

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
