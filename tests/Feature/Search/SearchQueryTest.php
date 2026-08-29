<?php

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\Search\Queries\SearchQueryService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('search matches owned tasks by title description project and label', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'Growth launch', 'key' => 'GROW']);
    $label = Label::factory()->forUser($owner)->create(['name' => 'Customer']);
    $task = Task::factory()->forProject($project)->create(['number' => 5, 'title' => 'Prepare onboarding']);
    $task->update(['description' => 'Customer handoff']);
    $task->labels()->attach($label);

    expect((new SearchQueryService)->search($owner, 'onboarding')['tasks']->modelKeys())->toBe([$task->id])
        ->and((new SearchQueryService)->search($owner, 'customer')['tasks']->modelKeys())->toBe([$task->id])
        ->and((new SearchQueryService)->search($owner, 'growth')['tasks']->modelKeys())->toBe([$task->id])
        ->and((new SearchQueryService)->search($owner, 'grow-5')['tasks']->modelKeys())->toBe([$task->id]);
});

test('search scopes projects and tasks to the owner and excludes deleted tasks', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownedProject = Project::factory()->for($owner)->create(['name' => 'Shared language']);
    $foreignProject = Project::factory()->for($other)->create(['name' => 'Shared language']);
    $ownedTask = Task::factory()->forProject($ownedProject)->create(['title' => 'Owned result']);
    $deletedTask = Task::factory()->forProject($ownedProject)->create(['title' => 'Deleted result']);
    $deletedTask->delete();
    Task::factory()->forProject($foreignProject)->create(['title' => 'Foreign result']);

    $results = (new SearchQueryService)->search($owner, 'shared');

    expect($results['projects']->modelKeys())->toBe([$ownedProject->id])
        ->and($results['tasks']->modelKeys())->toBe([$ownedTask->id]);
});

test('search caps each result type at twenty and ignores short terms', function (): void {
    $owner = User::factory()->create();
    Project::factory()->count(25)->for($owner)->create(['name' => 'Release project']);

    expect((new SearchQueryService)->search($owner, 'release')['projects'])->toHaveCount(20)
        ->and((new SearchQueryService)->search($owner, 'r')['projects'])->toHaveCount(0)
        ->and((new SearchQueryService)->search($owner, '   ')['projects'])->toHaveCount(0);
});

test('search request validates the query and renders results', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'Searchable project']);

    $this->actingAs($owner)->get(route('search', ['q' => 'searchable']))
        ->assertOk()
        ->assertSee('Search results')
        ->assertSee($project->name);

    $this->actingAs($owner)->get(route('search', ['q' => 'x']))
        ->assertOk()
        ->assertSee('Enter at least 2 characters to search.');
});
