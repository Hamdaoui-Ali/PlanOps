<?php

use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\CarbonImmutable;

uses(RefreshDatabase::class);

test('a task is overdue only after its user-local due date', function (): void {
    $task = Task::factory()->create([
        'due_on' => '2026-08-27',
        'status' => TaskStatus::IN_PROGRESS,
    ]);

    expect($task->isOverdueOn(CarbonImmutable::parse('2026-08-27')))->toBeFalse()
        ->and($task->isOverdueOn(CarbonImmutable::parse('2026-08-28')))->toBeTrue();
});

test('a future, undated, completed, or cancelled task is not overdue', function (TaskStatus $status, ?string $dueOn): void {
    $task = Task::factory()->create([
        'due_on' => $dueOn,
        'status' => $status,
    ]);

    expect($task->isOverdueOn(CarbonImmutable::parse('2026-08-28')))->toBeFalse();
})->with([
    'future due date' => [TaskStatus::IN_PROGRESS, '2026-08-29'],
    'null due date' => [TaskStatus::IN_PROGRESS, null],
    'done' => [TaskStatus::DONE, '2026-08-27'],
    'cancelled' => [TaskStatus::CANCELLED, '2026-08-27'],
]);
