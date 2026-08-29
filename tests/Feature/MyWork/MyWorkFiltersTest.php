<?php

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\MyWorkQuery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('My Work defaults to focused statuses and excludes deleted and foreign tasks', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $otherProject = Project::factory()->for(User::factory())->create();
    Task::factory()->forProject($project)->active()->create(['title' => 'Active work']);
    Task::factory()->forProject($project)->inReview()->create(['title' => 'Review work']);
    Task::factory()->forProject($project)->blocked()->create(['title' => 'Blocked work']);
    Task::factory()->forProject($project)->create(['title' => 'Not started work']);
    Task::factory()->forProject($project)->backlog()->create(['title' => 'Backlog work']);
    $deleted = Task::factory()->forProject($project)->done()->create(['title' => 'Deleted work']);
    $deleted->delete();
    Task::factory()->forProject($otherProject)->active()->create(['title' => 'Foreign work']);

    $tasks = (new MyWorkQuery)->paginate($owner);

    expect($tasks->getCollection()->pluck('title')->all())
        ->toHaveCount(4)
        ->toContain('Active work', 'Review work', 'Blocked work', 'Not started work')
        ->not->toContain('Backlog work', 'Deleted work', 'Foreign work');
});

test('My Work filters by project, priority, label, and explicit status', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $otherProject = Project::factory()->for($owner)->create();
    $label = Label::factory()->forUser($owner)->create(['name' => 'Urgent']);
    $match = Task::factory()->forProject($project)->active()->create(['priority' => TaskPriority::HIGH]);
    $match->labels()->attach($label);
    Task::factory()->forProject($otherProject)->active()->create(['priority' => TaskPriority::HIGH]);
    Task::factory()->forProject($project)->backlog()->create(['priority' => TaskPriority::HIGH]);

    $tasks = (new MyWorkQuery)->paginate($owner, [
        'project' => $project->id,
        'priority' => TaskPriority::HIGH->value,
        'label' => $label->id,
        'status' => TaskStatus::IN_PROGRESS->value,
    ]);

    expect($tasks->getCollection()->modelKeys())->toBe([$match->id]);
});

test('My Work due shortcuts use the owners local date', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-29 12:00:00', 'Africa/Casablanca'));
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $overdue = Task::factory()->forProject($project)->create(['due_on' => '2026-08-28']);
    $today = Task::factory()->forProject($project)->create(['due_on' => '2026-08-29']);
    $thisWeek = Task::factory()->forProject($project)->create(['due_on' => '2026-08-30']);

    expect((new MyWorkQuery)->paginate($owner, ['due' => 'overdue'])->getCollection()->modelKeys())->toBe([$overdue->id])
        ->and((new MyWorkQuery)->paginate($owner, ['due' => 'today'])->getCollection()->modelKeys())->toBe([$today->id])
        ->and((new MyWorkQuery)->paginate($owner, ['due' => 'this_week'])->getCollection()->modelKeys())->toBe([$thisWeek->id, $today->id]);

    CarbonImmutable::setTestNow();
});

test('the My Work route exposes only owned filter options and a useful empty state', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Project::factory()->for($owner)->create(['name' => 'Owned project']);
    Project::factory()->for($other)->create(['name' => 'Foreign project']);
    Label::factory()->forUser($owner)->create(['name' => 'Owned label']);
    Label::factory()->forUser($other)->create(['name' => 'Foreign label']);

    $this->actingAs($owner)->get('/my-work')
        ->assertOk()
        ->assertSee('In Progress')
        ->assertSee('In Review')
        ->assertSee('Blocked')
        ->assertSee('Not Started')
        ->assertSee('Project')
        ->assertSee('Due')
        ->assertSee('Recently updated')
        ->assertSee('No tracked work yet.')
        ->assertSee('Owned project')
        ->assertSee('Owned label')
        ->assertDontSee('Foreign project')
        ->assertDontSee('Foreign label');
});
