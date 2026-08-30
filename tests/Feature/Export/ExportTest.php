<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

it('streams an owned task csv with stable fields and serialized labels', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'PlanOps', 'key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create([
        'number' => 1,
        'title' => 'Ship export',
        'status' => TaskStatus::IN_PROGRESS,
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-05',
        'description' => 'This must not be exported.',
    ]);
    $child = Task::factory()->forProject($project)->withParent($parent)->create(['number' => 2, 'title' => 'Add tests']);
    $label = Label::factory()->forUser($user)->create(['name' => 'Important', 'normalized_name' => 'important']);
    $parent->labels()->attach($label);

    $response = $this->actingAs($user)->get(route('exports.tasks'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename=planops-tasks.csv');
    $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', $response->streamedContent())));

    expect(str_getcsv($lines[0]))->toBe(['key', 'project', 'parent', 'title', 'status', 'priority', 'due_on', 'labels', 'created_at', 'updated_at'])
        ->and($lines[1])->toContain('PLAN-1', 'PlanOps', 'Ship export', 'IN_PROGRESS', 'HIGH', 'Important')
        ->and($lines[2])->toContain('PLAN-2', 'PLAN-1', 'Add tests')
        ->and($response->streamedContent())->not->toContain('This must not be exported.');
});

it('exports only the current users projects and activity records', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'OWN']);
    $foreignProject = Project::factory()->for($other)->create(['key' => 'OTHER']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $foreignTask = Task::factory()->forProject($foreignProject)->create(['number' => 1]);
    TaskActivity::factory()->forTask($task)->create([
        'event_type' => TaskActivityType::STATUS_CHANGED,
        'field' => 'status',
        'old_value' => ['status' => 'NOT_STARTED'],
        'new_value' => ['status' => 'IN_PROGRESS'],
    ]);
    TaskActivity::factory()->forTask($foreignTask)->create(['event_type' => TaskActivityType::TASK_CREATED]);

    $this->actingAs($user)->get(route('exports.projects'))->assertOk()->assertSee('OWN')->assertDontSee('OTHER');
    $this->actingAs($user)->get(route('exports.activity', ['format' => 'json']))
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonCount(1)
        ->assertJsonPath('0.task_key', 'OWN-'.$task->number)
        ->assertJsonPath('0.event_type', 'STATUS_CHANGED');
});
