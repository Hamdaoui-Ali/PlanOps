<?php

use App\Domain\Tasks\Enums\TaskStatus;

test('TaskStatus contains the complete workflow', function () {
    expect(array_column(TaskStatus::cases(), 'value'))->toBe([
        'BACKLOG',
        'NOT_STARTED',
        'IN_PROGRESS',
        'IN_REVIEW',
        'BLOCKED',
        'DONE',
        'CANCELLED',
    ]);
});

test('TaskStatus maps to stable analytics categories', function () {
    expect(TaskStatus::BACKLOG->category())->toBe('PLANNED');
    expect(TaskStatus::NOT_STARTED->category())->toBe('PLANNED');
    expect(TaskStatus::IN_PROGRESS->category())->toBe('ACTIVE');
    expect(TaskStatus::IN_REVIEW->category())->toBe('ACTIVE');
    expect(TaskStatus::BLOCKED->category())->toBe('ACTIVE');
    expect(TaskStatus::DONE->category())->toBe('TERMINAL');
    expect(TaskStatus::CANCELLED->category())->toBe('TERMINAL');
});
