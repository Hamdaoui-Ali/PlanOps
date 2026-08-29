<?php

use App\Domain\Identity\Enums\WeekStartDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard renders the selected week period', function (): void {
    $user = User::factory()->create();
    $user->preference()->create(['timezone' => 'Africa/Casablanca', 'week_start_day' => WeekStartDay::MONDAY]);

    $this->actingAs($user)->get(route('dashboard', ['period' => 'week']))
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Week of');
});

test('dashboard rejects an invalid reversed custom range', function (): void {
    $this->actingAs(User::factory()->create())->get(route('dashboard', [
        'period' => 'custom',
        'from' => '2026-09-10',
        'until' => '2026-09-01',
    ]))->assertSessionHasErrors('until');
});
