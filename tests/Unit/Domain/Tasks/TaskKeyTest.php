<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;

uses(RefreshDatabase::class);

test('task display keys are derived from the project key and task number', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    expect((new TaskKeyQuery)->displayKey($task))->toBe('PLAN-1');
    expect($task->getRawOriginal('display_key'))->toBeNull();
});

test('task display keys load the project key when the project relation is not preloaded', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $unloadedTask = Task::query()->findOrFail($task->id);

    expect($unloadedTask->relationLoaded('project'))->toBeFalse()
        ->and((new TaskKeyQuery)->displayKey($unloadedTask))->toBe('PLAN-1');
});

test('task display keys reject tasks without a valid project identity', function (): void {
    $task = new Task([
        'number' => 1,
        'title' => 'Missing project identity',
    ]);

    expect(fn (): string => (new TaskKeyQuery)->displayKey($task))
        ->toThrow(LogicException::class);
});

test('task display keys reject a preloaded project owned by another user', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $foreignProject = Project::factory()->for($other)->create(['key' => 'OTHER']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $task->setRelation('project', $foreignProject);

    expect(fn (): string => (new TaskKeyQuery)->displayKey($task))
        ->toThrow(LogicException::class);
});

test('task display keys reject a stale preloaded project from another project owned by the task owner', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $staleProject = Project::factory()->for($owner)->create(['key' => 'OTHER']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $task->setRelation('project', $staleProject);

    expect(fn (): string => (new TaskKeyQuery)->displayKey($task))
        ->toThrow(LogicException::class);
});
