<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can view a board with readable task metadata and accessible status controls', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    Task::factory()->forProject($project)->active()->create([
        'number' => 1,
        'title' => 'Ship release',
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-20',
    ]);

    $this->actingAs($owner)->get(route('projects.board', $project))
        ->assertOk()
        ->assertSee('In Progress')
        ->assertSee('PLAN-1')
        ->assertSee('Ship release')
        ->assertSee('High')
        ->assertSee('Sep 20, 2026')
        ->assertSee('Move to')
        ->assertSee('Back to overview');
});

test('a foreign project board is unavailable', function (): void {
    $owner = User::factory()->create();
    $foreignProject = Project::factory()->for(User::factory())->create();

    $this->actingAs($owner)->get(route('projects.board', $foreignProject))->assertNotFound();
});

test('cancelled tasks are hidden unless the board filter is enabled', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    Task::factory()->forProject($project)->cancelled()->create(['number' => 1, 'title' => 'Canceled work']);

    $this->actingAs($owner)->get(route('projects.board', $project))
        ->assertOk()
        ->assertDontSee('Canceled work');

    $this->actingAs($owner)->get(route('projects.board', [$project, 'include_cancelled' => 1]))
        ->assertOk()
        ->assertSee('Canceled work');
});
