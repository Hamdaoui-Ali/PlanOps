<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;

uses(RefreshDatabase::class);

function immutableActivityFixture(): TaskActivity
{
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    return TaskActivity::factory()->forTask($task)->create();
}

test('persisted activity rows reject model updates', function (): void {
    $activity = immutableActivityFixture();

    expect(fn (): mixed => $activity->update(['field' => 'status']))
        ->toThrow(LogicException::class, 'append-only');
    expect($activity->fresh()->field)->toBeNull();
});

test('persisted activity rows reject model deletes', function (): void {
    $activity = immutableActivityFixture();

    expect(fn (): mixed => $activity->delete())
        ->toThrow(LogicException::class, 'append-only');
    expect(TaskActivity::query()->whereKey($activity->getKey())->exists())->toBeTrue();
});

test('activity created_at is exposed as an immutable date-time', function (): void {
    $activity = immutableActivityFixture();

    expect($activity->created_at)->toBeInstanceOf(DateTimeImmutable::class);
});
