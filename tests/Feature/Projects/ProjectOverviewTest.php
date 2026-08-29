<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can view a project overview with its non-deleted top-level tasks', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create([
        'number' => 1,
        'title' => 'Plan the release',
    ]);

    $response = $this->actingAs($owner)->get(route('projects.show', $project));

    $response->assertOk()
        ->assertSee('Plan the release')
        ->assertSee('PLAN-1')
        ->assertSee('0%')
        ->assertSee('0 of 1 tasks done');
});

test('a project overview excludes soft deleted tasks and foreign projects', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $deleted = Task::factory()->forProject($project)->create(['title' => 'Deleted task']);
    $deleted->delete();
    $foreignProject = Project::factory()->for($other)->create();

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Deleted task');

    $this->actingAs($owner)->get(route('projects.show', $foreignProject))
        ->assertNotFound();
});

test('the project overview exposes collapsed direct subtasks with independent metadata', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create([
        'number' => 2,
        'title' => 'Prepare checklist',
        'status' => TaskStatus::IN_PROGRESS,
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-20',
    ]);
    Task::factory()->forProject($project)->withParent($parent)->done()->create(['number' => 3]);

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Show subtasks')
        ->assertSee('1 of 2 subtasks done')
        ->assertSee('Prepare checklist')
        ->assertSee(route('tasks.show', $child), false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('aria-controls="subtasks-'.$parent->id.'"', false);
});
