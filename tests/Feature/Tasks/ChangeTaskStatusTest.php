<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('changing a top-level task to done updates project progress', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create(['status' => TaskStatus::NOT_STARTED]);

    $response = $this->actingAs($owner)->post(route('tasks.status', $task), [
        'status' => TaskStatus::DONE->value,
    ]);

    $response->assertRedirect(route('projects.show', $project, absolute: false));
    expect($task->fresh()->status)->toBe(TaskStatus::DONE)
        ->and($task->fresh()->completed_at)->not->toBeNull()
        ->and($project->fresh()->progress_percent)->toBe(100)
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::STATUS_CHANGED)->count())->toBe(1);
});

test('reopening a completed task clears its current completion timestamp', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->done()->create();

    $this->actingAs($owner)->post(route('tasks.status', $task), [
        'status' => TaskStatus::IN_PROGRESS->value,
    ])->assertRedirect(route('projects.show', $project, absolute: false));

    expect($task->fresh()->status)->toBe(TaskStatus::IN_PROGRESS)
        ->and($task->fresh()->completed_at)->toBeNull()
        ->and(TaskActivity::query()->where('event_type', TaskActivityType::STATUS_CHANGED)->count())->toBe(1);
});

test('subtasks and cancelled top-level tasks do not change project progress', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $done = Task::factory()->forProject($project)->done()->create();
    Task::factory()->forProject($project)->withParent($done)->create(['status' => TaskStatus::NOT_STARTED]);
    Task::factory()->forProject($project)->cancelled()->create();

    expect($project->fresh()->progress_percent)->toBe(100);
});

test('a foreign task status URL is not available to another owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($other)->create();
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($owner)->post(route('tasks.status', $task), [
        'status' => TaskStatus::DONE->value,
    ])->assertNotFound();
});
