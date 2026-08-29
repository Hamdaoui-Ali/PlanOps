<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can view a task detail page with its direct subtasks', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create([
        'number' => 2,
        'title' => 'Prepare checklist',
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-20',
    ]);

    $this->actingAs($owner)->get(route('tasks.show', $parent))
        ->assertOk()
        ->assertSee('PLAN-1')
        ->assertSee('Ship release')
        ->assertSee('Prepare checklist')
        ->assertSee('High')
        ->assertSee('Sep 20, 2026');
});

test('a subtask detail page does not show a nested subtasks section', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create([
        'number' => 2,
        'title' => 'Prepare checklist',
    ]);

    $this->actingAs($owner)->get(route('tasks.show', $child))
        ->assertOk()
        ->assertSee('Subtask of')
        ->assertDontSee('id="task-subtasks-heading"', false)
        ->assertDontSee('No subtasks yet.');
});

test('a foreign task detail URL is not available', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($other)->create();

    $this->actingAs($owner)->get(route('tasks.show', $task))->assertNotFound();
});

test('a subtask detail page identifies its parent task', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create(['number' => 2, 'title' => 'Prepare checklist']);

    $this->actingAs($owner)->get(route('tasks.show', $child))
        ->assertOk()
        ->assertSee('Subtask of')
        ->assertSee('PLAN-1 · Ship release');
});
