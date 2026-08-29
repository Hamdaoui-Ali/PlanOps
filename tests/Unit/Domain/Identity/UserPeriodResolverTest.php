<?php

use App\Domain\Identity\Enums\WeekStartDay;
use App\Domain\Identity\Services\UserPeriodResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('today resolves the users local calendar day to a half-open UTC interval', function (): void {
    $user = User::factory()->create();
    $user->preference()->create(['timezone' => 'Africa/Casablanca']);
    $now = CarbonImmutable::parse('2026-08-29 12:30:00', 'Africa/Casablanca');

    $period = (new UserPeriodResolver)->today($user, $now);

    expect($period->bucket)->toBe('day')
        ->and($period->start->toIso8601String())->toBe('2026-08-28T23:00:00+00:00')
        ->and($period->end->toIso8601String())->toBe('2026-08-29T23:00:00+00:00')
        ->and($period->label)->toBe('Today');
});

test('week respects the users configured Monday or Sunday start', function (WeekStartDay $weekStart, string $expectedStart, string $expectedEnd): void {
    $user = User::factory()->create();
    $user->preference()->create(['timezone' => 'Africa/Casablanca', 'week_start_day' => $weekStart]);
    $now = CarbonImmutable::parse('2026-08-29 12:30:00', 'Africa/Casablanca');

    $period = (new UserPeriodResolver)->week($user, $now);

    expect($period->bucket)->toBe('week')
        ->and($period->start->toIso8601String())->toBe($expectedStart)
        ->and($period->end->toIso8601String())->toBe($expectedEnd);
})->with([
    'Monday' => [WeekStartDay::MONDAY, '2026-08-23T23:00:00+00:00', '2026-08-30T23:00:00+00:00'],
    'Sunday' => [WeekStartDay::SUNDAY, '2026-08-22T23:00:00+00:00', '2026-08-29T23:00:00+00:00'],
]);

test('month and year resolve calendar boundaries in the users timezone', function (): void {
    $user = User::factory()->create();
    $user->preference()->create(['timezone' => 'Europe/London']);
    $now = CarbonImmutable::parse('2026-08-29 12:30:00', 'Europe/London');
    $resolver = new UserPeriodResolver;

    $month = $resolver->month($user, $now);
    $year = $resolver->year($user, $now);

    expect($month->bucket)->toBe('month')
        ->and($month->start->toIso8601String())->toBe('2026-08-01T00:00:00+00:00')
        ->and($month->end->toIso8601String())->toBe('2026-09-01T00:00:00+00:00')
        ->and($year->bucket)->toBe('month')
        ->and($year->start->toIso8601String())->toBe('2026-01-01T00:00:00+00:00')
        ->and($year->end->toIso8601String())->toBe('2027-01-01T00:00:00+00:00');
});

test('custom periods include the local end date and reject reversed ranges', function (): void {
    $user = User::factory()->create();
    $user->preference()->create(['timezone' => 'Africa/Casablanca']);
    $resolver = new UserPeriodResolver;

    $period = $resolver->custom($user, '2026-08-29', '2026-08-31');

    expect($period->start->toIso8601String())->toBe('2026-08-28T23:00:00+00:00')
        ->and($period->end->toIso8601String())->toBe('2026-08-31T23:00:00+00:00');

    expect(fn (): mixed => $resolver->custom($user, '2026-09-01', '2026-08-31'))
        ->toThrow(InvalidArgumentException::class);
});
