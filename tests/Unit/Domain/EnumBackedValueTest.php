<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Identity\Enums\DensityPreference;
use App\Domain\Identity\Enums\ThemePreference;
use App\Domain\Identity\Enums\WeekStartDay;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Tasks\Enums\TaskPriority;

test('domain enums expose every documented backed value', function (string $enum, array $values): void {
    expect(array_column($enum::cases(), 'value'))->toBe($values);
})->with([
    'project status' => [ProjectStatus::class, ['PLANNED', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED']],
    'task priority' => [TaskPriority::class, ['LOW', 'MEDIUM', 'HIGH', 'URGENT']],
    'task activity type' => [TaskActivityType::class, [
        'TASK_CREATED',
        'TASK_UPDATED',
        'STATUS_CHANGED',
        'PRIORITY_CHANGED',
        'DUE_DATE_CHANGED',
        'LABEL_ADDED',
        'LABEL_REMOVED',
        'SUBTASK_CREATED',
        'TASK_MOVED_PROJECT',
        'TASK_DELETED',
        'TASK_RESTORED',
    ]],
    'theme preference' => [ThemePreference::class, ['SYSTEM', 'LIGHT', 'DARK']],
    'density preference' => [DensityPreference::class, ['COMFORTABLE', 'COMPACT']],
    'week start day' => [WeekStartDay::class, ['MONDAY', 'SUNDAY']],
]);
