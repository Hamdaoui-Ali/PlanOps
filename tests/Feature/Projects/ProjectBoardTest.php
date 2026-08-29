<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
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
        ->assertSee('Back to overview')
        ->assertSee('Move up');
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

test('a board status change uses the task action and redirects to the board', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create(['status' => TaskStatus::NOT_STARTED]);

    $this->actingAs($owner)->post(route('projects.board.tasks.status', [$project, $task]), [
        'status' => TaskStatus::IN_PROGRESS->value,
    ])->assertRedirect(route('projects.board', $project, absolute: false));

    expect($task->fresh()->status)->toBe(TaskStatus::IN_PROGRESS)
        ->and(TaskActivity::query()->where('task_id', $task->id)->where('event_type', TaskActivityType::STATUS_CHANGED)->exists())->toBeTrue();
});

test('a board status change rejects a task from another project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $otherProject = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($otherProject)->create();

    $this->actingAs($owner)->post(route('projects.board.tasks.status', [$project, $task]), [
        'status' => TaskStatus::IN_PROGRESS->value,
    ])->assertNotFound();
});

test('a valid board reorder redirects and persists the requested order', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $first = Task::factory()->forProject($project)->active()->create(['position' => 0]);
    $second = Task::factory()->forProject($project)->active()->create(['position' => 1]);

    $this->actingAs($owner)->post(route('projects.board.reorder', $project), [
        'status' => TaskStatus::IN_PROGRESS->value,
        'ordered_task_ids' => [$second->id, $first->id],
    ])->assertRedirect(route('projects.board', $project, absolute: false));

    expect($second->fresh()->position)->toBe(0)
        ->and($first->fresh()->position)->toBe(1);
});
