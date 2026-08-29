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
    Task::factory()->forProject($project)->active()->create(['title' => 'Alpha', 'priority' => TaskPriority::LOW, 'position' => 2]);
    Task::factory()->forProject($project)->active()->create(['title' => 'Zulu', 'priority' => TaskPriority::URGENT, 'position' => 1]);

    expect((new MyWorkQuery)->paginate($owner, ['sort' => $sort])->getCollection()->pluck('title')->all())->toBe($expectedTitles);
})->with([
    'created' => ['created', ['Alpha', 'Zulu']],
    'priority' => ['priority', ['Zulu', 'Alpha']],
    'task key' => ['task_key', ['Alpha', 'Zulu']],
    'project' => ['project', ['Alpha', 'Zulu']],
]);

test('an unsupported My Work sort is rejected by the request', function (): void {
    $this->actingAs(User::factory()->create())->get('/my-work?sort=unsafe_column')
        ->assertSessionHasErrors('sort');
});
