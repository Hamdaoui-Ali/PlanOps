<?php

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\ProjectTaskListQuery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the project task list includes visible parents and direct subtasks only', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $foreignProject = Project::factory()->for(User::factory())->create();
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Parent task']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create(['number' => 2, 'title' => 'Child task']);
    $deleted = Task::factory()->forProject($project)->create(['title' => 'Deleted task']);
    $deleted->delete();
    Task::factory()->forProject($foreignProject)->create(['title' => 'Foreign task']);

    $page = (new ProjectTaskListQuery)->paginate($owner, $project);

    expect($page->getCollection()->modelKeys())->toEqualCanonicalizing([$parent->id, $child->id]);

    $parentRow = $page->getCollection()->firstWhere('id', $parent->id);

    expect($parentRow->relationLoaded('project'))->toBeTrue()
        ->and($parentRow->relationLoaded('parent'))->toBeTrue()
        ->and($parentRow->relationLoaded('labels'))->toBeTrue()
        ->and($parentRow->children_count)->toBe(1);
});

test('the project task list filters by status priority label and due state', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-29 12:00:00', 'Africa/Casablanca'));
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $label = Label::factory()->forUser($owner)->create();
    $match = Task::factory()->forProject($project)->active()->create([
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-08-29',
    ]);
    $match->labels()->attach($label);
    Task::factory()->forProject($project)->backlog()->create(['priority' => TaskPriority::HIGH, 'due_on' => '2026-08-29']);
    Task::factory()->forProject($project)->active()->create(['priority' => TaskPriority::LOW, 'due_on' => null]);

    $page = (new ProjectTaskListQuery)->paginate($owner, $project, [
        'status' => TaskStatus::IN_PROGRESS->value,
        'priority' => TaskPriority::HIGH->value,
        'label' => $label->id,
        'due' => 'today',
    ]);

    expect($page->getCollection()->modelKeys())->toBe([$match->id]);
    CarbonImmutable::setTestNow();
});

test('the project task list supports safe deterministic sorts', function (string $sort, array $expected): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    Task::factory()->forProject($project)->create(['title' => 'Zulu', 'number' => 2, 'position' => 1, 'priority' => TaskPriority::URGENT]);
    Task::factory()->forProject($project)->create(['title' => 'Alpha', 'number' => 1, 'position' => 0, 'priority' => TaskPriority::LOW]);

    expect((new ProjectTaskListQuery)->paginate($owner, $project, ['sort' => $sort])->getCollection()->pluck('title')->all())->toBe($expected);
})->with([
    'created' => ['created', ['Alpha', 'Zulu']],
    'priority' => ['priority', ['Zulu', 'Alpha']],
    'due' => ['due', ['Alpha', 'Zulu']],
    'task key' => ['task_key', ['Alpha', 'Zulu']],
]);

test('an owner can view the project task list with owned filter options and empty states', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'Plan project', 'key' => 'PLAN']);
    Label::factory()->forUser($owner)->create(['name' => 'Owned label']);
    Label::factory()->forUser($other)->create(['name' => 'Foreign label']);

    $this->actingAs($owner)->get(route('projects.tasks.index', $project))
        ->assertOk()
        ->assertSee('Plan project')
        ->assertSee('Owned label')
        ->assertDontSee('Foreign label')
        ->assertSee('This project has no tracked work yet.');
});

test('a foreign project task list is unavailable', function (): void {
    $owner = User::factory()->create();
    $foreignProject = Project::factory()->for(User::factory())->create();

    $this->actingAs($owner)->get(route('projects.tasks.index', $foreignProject))->assertNotFound();
});

test('project task quick actions preserve the project list context safely', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create();
    $query = http_build_query([
        'return_context' => 'project-tasks',
        'project' => '999999',
        'sort' => 'priority',
        'status' => TaskStatus::IN_PROGRESS->value,
        'unsafe_column' => 'secret',
    ]);

    $this->actingAs($owner)->post(route('tasks.status', $task).'?'.$query, [
        'status' => TaskStatus::IN_PROGRESS->value,
    ])->assertRedirect(route('projects.tasks.index', [
        'project' => $project->id,
        'sort' => 'priority',
        'status' => TaskStatus::IN_PROGRESS->value,
    ], absolute: false));

    $this->actingAs($owner)->patch(route('tasks.priority', $task).'?'.$query, [
        'priority' => TaskPriority::HIGH->value,
    ])->assertRedirect(route('projects.tasks.index', [
        'project' => $project->id,
        'sort' => 'priority',
        'status' => TaskStatus::IN_PROGRESS->value,
    ], absolute: false));
});
