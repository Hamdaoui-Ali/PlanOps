<?php

use App\Domain\Identity\Enums\DensityPreference;
use App\Domain\Identity\Enums\ThemePreference;
use App\Domain\Identity\Enums\WeekStartDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a user receives the documented preference defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertSee('Africa/Casablanca')
        ->assertSee('Europe/London')
        ->assertSee('MONDAY')
        ->assertSee('SYSTEM')
        ->assertSee('COMFORTABLE');
});

test('the settings layout applies default theme and density classes at the document root', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings')
        ->assertOk()
        ->assertSee('theme-system')
        ->assertSee('density-comfortable');
});

test('the settings form renders persisted enum values as selected options', function () {
    $user = User::factory()->create();

    $user->preference()->create([
        'week_start_day' => WeekStartDay::SUNDAY,
        'theme' => ThemePreference::DARK,
        'density' => DensityPreference::COMPACT,
    ]);

    $this->actingAs($user)
        ->get('/settings')
        ->assertOk()
        ->assertSee('theme-dark')
        ->assertSee('density-compact')
        ->assertSee('<option value="SUNDAY" selected', false)
        ->assertSee('<option value="DARK" selected', false)
        ->assertSee('<option value="COMPACT" selected', false);
});

test('an authenticated user can persist valid preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'timezone' => 'Europe/London',
            'week_start_day' => 'SUNDAY',
            'theme' => 'DARK',
            'density' => 'COMPACT',
        ])
        ->assertRedirect('/settings');

    $this->assertDatabaseHas('user_preferences', [
        'user_id' => $user->id,
        'timezone' => 'Europe/London',
        'week_start_day' => 'SUNDAY',
        'theme' => 'DARK',
        'density' => 'COMPACT',
    ]);
});

test('a preference update does not alter another users preferences', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUser->preference()->create([
        'timezone' => 'America/Toronto',
        'week_start_day' => 'SUNDAY',
        'theme' => 'LIGHT',
        'density' => 'COMPACT',
    ]);

    $this->actingAs($user)
        ->patch('/settings/preferences', [
            'timezone' => 'Europe/London',
            'week_start_day' => 'MONDAY',
            'theme' => 'DARK',
            'density' => 'COMFORTABLE',
        ])
        ->assertRedirect('/settings');

    $this->assertDatabaseHas('user_preferences', [
        'user_id' => $otherUser->id,
        'timezone' => 'America/Toronto',
        'week_start_day' => 'SUNDAY',
        'theme' => 'LIGHT',
        'density' => 'COMPACT',
    ]);
});

test('preference updates reject values outside the documented enums', function (string $field, string $value) {
    $this->actingAs(User::factory()->create())
        ->from('/settings')
        ->patch('/settings/preferences', [$field => $value])
        ->assertRedirect('/settings')
        ->assertSessionHasErrors($field);
})->with([
    'week start day' => ['week_start_day', 'SATURDAY'],
    'theme' => ['theme', 'MIDNIGHT'],
    'density' => ['density', 'SPACIOUS'],
]);
