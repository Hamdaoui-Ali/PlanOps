<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\ProjectBoardQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the board groups owned non-deleted top-level tasks by workflow status', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $foreignProject = Project::factory()->for(User::factory())->create();

    $backlog = Task::factory()->forProject($project)->backlog()->create(['number' => 2, 'position' => 1]);
    Task::factory()->forProject($project)->backlog()->create(['number' => 1, 'position' => 0]);
    Task::factory()->forProject($project)->withParent($backlog)->create(['number' => 3]);
    $deleted = Task::factory()->forProject($project)->done()->create(['number' => 4]);
    $deleted->delete();
    Task::factory()->forProject($foreignProject)->active()->create(['number' => 5]);

    $columns = (new ProjectBoardQuery)->for($owner, $project);

    expect(array_keys($columns))->toBe([
        TaskStatus::BACKLOG->value,
        TaskStatus::NOT_STARTED->value,
        TaskStatus::IN_PROGRESS->value,
        TaskStatus::IN_REVIEW->value,
        TaskStatus::BLOCKED->value,
        TaskStatus::DONE->value,
    ])
        ->and($columns[TaskStatus::BACKLOG->value]->pluck('number')->all())->toBe([1, 2])
        ->and($columns[TaskStatus::DONE->value])->toBeEmpty();
});

test('the board query can include cancelled top-level tasks explicitly', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    Task::factory()->forProject($project)->cancelled()->create(['number' => 1]);

    $columns = (new ProjectBoardQuery)->for($owner, $project, includeCancelled: true);

    expect(array_key_exists(TaskStatus::CANCELLED->value, $columns))->toBeTrue()
        ->and($columns[TaskStatus::CANCELLED->value])->toHaveCount(1);
});
