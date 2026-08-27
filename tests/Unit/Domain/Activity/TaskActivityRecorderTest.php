<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;

uses(RefreshDatabase::class);

test('records task ownership context and stable enum payload values', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::STATUS_CHANGED,
        'status',
        TaskStatus::IN_PROGRESS,
        TaskStatus::DONE,
    );

    expect($activity->user_id)->toBe($owner->id);
    expect($activity->project_id)->toBe($project->id);
    expect($activity->task_id)->toBe($task->id);
    expect($activity->getRawOriginal('event_type'))->toBe('STATUS_CHANGED');
    expect($activity->old_value)->toBe(['status' => 'IN_PROGRESS']);
    expect($activity->new_value)->toBe(['status' => 'DONE']);
});

test('redacts title and description values from generic updates', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::TASK_UPDATED,
        'description',
        'private old description',
        'private new description',
        ['description' => 'private metadata', 'is_reopen' => true],
    );

    expect($activity->old_value)->toBeNull();
    expect($activity->new_value)->toBeNull();
    expect($activity->metadata)->toBe(['is_reopen' => true]);
});

test('redacts title values from generic updates', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::TASK_UPDATED,
        'title',
        'private old title',
        'private new title',
        ['title' => 'private metadata', 'is_reopen' => true],
    );

    expect($activity->old_value)->toBeNull();
    expect($activity->new_value)->toBeNull();
    expect($activity->metadata)->toBe(['is_reopen' => true]);
});

test('normalizes nested enums and date-times while preserving metadata booleans', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::TASK_UPDATED,
        'priority',
        ['priority' => TaskPriority::MEDIUM],
        ['priority' => TaskPriority::HIGH],
        [
            'is_reopen' => false,
            'changed_at' => Carbon::parse('2026-08-27 14:00:00', 'Africa/Casablanca'),
        ],
    );

    expect($activity->old_value)->toBe(['priority' => 'MEDIUM']);
    expect($activity->new_value)->toBe(['priority' => 'HIGH']);
    expect($activity->metadata['is_reopen'])->toBeFalse();
    expect($activity->metadata['changed_at'])->toBe('2026-08-27T13:00:00+00:00');
});

test('rejects an unsaved task because it has no historical context', function (): void {
    expect(fn (): mixed => (new TaskActivityRecorder)->record(
        new Task,
        TaskActivityType::TASK_CREATED,
        null,
        null,
        ['status' => TaskStatus::NOT_STARTED],
    ))->toThrow(LogicException::class);
});
