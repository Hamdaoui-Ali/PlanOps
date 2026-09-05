<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\MyWorkQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('My Work supports deterministic safe sort options', function (string $sort, array $expectedTitles): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    Task::factory()->forProject($project)->active()->create(['title' => 'Alpha', 'number' => 1, 'created_at' => '2026-08-29 11:00:00', 'priority' => TaskPriority::LOW, 'position' => 2]);
    Task::factory()->forProject($project)->active()->create(['title' => 'Zulu', 'number' => 2, 'created_at' => '2026-08-29 12:00:00', 'priority' => TaskPriority::URGENT, 'position' => 1]);

    expect((new MyWorkQuery)->paginate($owner, ['sort' => $sort])->getCollection()->pluck('title')->all())->toBe($expectedTitles);
})->with([
    'created' => ['created', ['Zulu', 'Alpha']],
    'priority' => ['priority', ['Zulu', 'Alpha']],
    'task key' => ['task_key', ['Alpha', 'Zulu']],
    'project' => ['project', ['Alpha', 'Zulu']],
]);

test('an unsupported My Work sort is rejected by the request', function (): void {
    $this->actingAs(User::factory()->create())->get('/my-work?sort=unsafe_column')
        ->assertSessionHasErrors('sort');
});

test('quick actions preserve only the safe My Work context', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create();
    $query = http_build_query([
        'return_context' => 'my-work',
        'project' => $project->id,
        'sort' => 'priority',
        'unsafe_column' => 'secret',
    ]);

    $this->actingAs($owner)->post(route('tasks.status', $task).'?'.$query, [
        'status' => 'IN_PROGRESS',
    ])->assertRedirect(route('my-work', ['project' => $project->id, 'sort' => 'priority'], absolute: false));

    $this->actingAs($owner)->patch(route('tasks.priority', $task).'?'.$query, [
        'priority' => TaskPriority::HIGH->value,
    ])->assertRedirect(route('my-work', ['project' => $project->id, 'sort' => 'priority'], absolute: false));

    expect($task->fresh()->priority)->toBe(TaskPriority::HIGH);
});
