<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\ReorderTasks;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('reordering tasks assigns contiguous positions in the requested order', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $first = Task::factory()->forProject($project)->active()->create(['position' => 0]);
    $second = Task::factory()->forProject($project)->active()->create(['position' => 1]);
    $third = Task::factory()->forProject($project)->active()->create(['position' => 2]);

    (new ReorderTasks)->handle($owner, $project, TaskStatus::IN_PROGRESS, [$third->id, $first->id, $second->id]);

    expect($third->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1)
        ->and($second->fresh()->position)->toBe(2);
});

test('reordering rejects an id outside the project without changing positions', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->active()->create(['position' => 4]);
    $foreign = Task::factory()->forProject(Project::factory()->for(User::factory())->create())->active()->create();

    expect(fn () => (new ReorderTasks)->handle($owner, $project, TaskStatus::IN_PROGRESS, [$task->id, $foreign->id]))
        ->toThrow(ValidationException::class);

    expect($task->fresh()->position)->toBe(4);
});

test('reordering rejects children, deleted tasks, and wrong statuses', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $parent = Task::factory()->forProject($project)->active()->create();
    $child = Task::factory()->forProject($project)->withParent($parent)->active()->create();
    $deleted = Task::factory()->forProject($project)->active()->create();
    $deleted->delete();
    $wrongStatus = Task::factory()->forProject($project)->backlog()->create();

    foreach ([[$child->id], [$deleted->id], [$wrongStatus->id]] as $ids) {
        expect(fn () => (new ReorderTasks)->handle($owner, $project, TaskStatus::IN_PROGRESS, $ids))
            ->toThrow(ValidationException::class);
    }
});
