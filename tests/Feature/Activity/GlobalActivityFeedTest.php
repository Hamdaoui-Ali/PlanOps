<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('an owner can view a readable global activity feed with safe filters', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'Plan project', 'key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $otherTask = Task::factory()->forProject(Project::factory()->for($other)->create())->create(['title' => 'Foreign work']);

    $activity = TaskActivity::factory()->forTask($task)->statusChanged()->create([
        'field' => 'status',
        'old_value' => ['value' => 'NOT_STARTED'],
        'new_value' => ['value' => 'IN_PROGRESS'],
        'created_at' => Carbon::parse('2026-08-29 10:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($otherTask)->statusChanged()->create();

    $this->actingAs($owner)->get(route('activity', [
        'project_id' => $project->id,
        'event_type' => TaskActivityType::STATUS_CHANGED->value,
    ]))
        ->assertOk()
        ->assertSee('Activity')
        ->assertSee('PLAN-1')
        ->assertSee('Ship release')
        ->assertSee('Status changed')
        ->assertSee('Not started')
        ->assertSee('In progress')
        ->assertDontSee('Foreign work')
        ->assertDontSee(json_encode($activity->new_value), false);
});

test('the global activity feed exposes a useful empty state', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('activity'))
        ->assertOk()
        ->assertSee('No activity recorded yet.');
});

test('activity filters reject unsupported event types', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('activity', ['event_type' => 'SECRET_EVENT']))
        ->assertSessionHasErrors('event_type');
});
