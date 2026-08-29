<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('project progress counts completed eligible top level tasks', function (): void {
    $project = Project::factory()->create();
    Task::factory()->forProject($project)->done()->create();
    Task::factory()->forProject($project)->create(['status' => TaskStatus::IN_PROGRESS]);
    Task::factory()->forProject($project)->cancelled()->create();

    expect($project->fresh()->progressPercent())->toBe(50);
});

test('subtasks do not change project progress', function (): void {
    $project = Project::factory()->create();
    $parent = Task::factory()->forProject($project)->create(['status' => TaskStatus::IN_PROGRESS]);
    Task::factory()->forProject($project)->withParent($parent)->done()->create();

    expect($project->fresh()->progressPercent())->toBe(0);
});

test('a project without eligible tasks reports zero progress', function (): void {
    $project = Project::factory()->create();

    expect($project->progressCounts())->toBe([
        'eligible_task_count' => 0,
        'completed_task_count' => 0,
    ])->and($project->progressPercent())->toBe(0);
});
