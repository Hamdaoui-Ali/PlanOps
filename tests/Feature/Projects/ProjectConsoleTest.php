<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the projects index renders the active owner ledger with project details and actions', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $active = Project::factory()->for($owner)->active()->create([
        'name' => 'Website Refresh',
        'key' => 'WEB',
    ]);
    $archived = Project::factory()->for($owner)->create([
        'name' => 'Retired Migration',
        'key' => 'OLD',
        'archived_at' => now(),
    ]);
    $foreign = Project::factory()->for($other)->active()->create([
        'name' => 'Other Team Project',
        'key' => 'OTHER',
    ]);
    Task::factory()->forProject($active)->done()->create();
    Task::factory()->forProject($active)->create(['status' => TaskStatus::IN_PROGRESS]);

    $response = $this->actingAs($owner)->get(route('projects.index'));

    $response->assertOk()
        ->assertSee('Website Refresh')
        ->assertSee('WEB')
        ->assertSee('ACTIVE')
        ->assertSee('1 of 2 done')
        ->assertSee('50%')
        ->assertSee('New project')
        ->assertSee('Find a project')
        ->assertSee('Open project')
        ->assertSee(route('projects.edit', $active, absolute: false), false)
        ->assertDontSee($archived->name)
        ->assertDontSee($foreign->name);
});

test('the archived projects filter exposes only the owners archived projects', function (): void {
    $owner = User::factory()->create();
    $archived = Project::factory()->for($owner)->onHold()->create([
        'name' => 'Archived Discovery',
        'key' => 'ARC',
        'archived_at' => now(),
    ]);
    $active = Project::factory()->for($owner)->active()->create([
        'name' => 'Active Delivery',
        'key' => 'LIVE',
    ]);

    $response = $this->actingAs($owner)->get(route('projects.index', ['archived' => 'archived']));

    $response->assertOk()
        ->assertSee($archived->name)
        ->assertSee('ON HOLD')
        ->assertDontSee($active->name);
});

test('filtered projects with no matches explain the result and offer a reset path', function (): void {
    $owner = User::factory()->create();
    Project::factory()->for($owner)->active()->create(['name' => 'Existing Delivery', 'key' => 'LIVE']);

    $response = $this->actingAs($owner)->get(route('projects.index', ['search' => 'no-match']));

    $response->assertOk()
        ->assertSee('No projects match your current filters.')
        ->assertSee('Reset filters')
        ->assertSee(route('projects.index', absolute: false), false);
});

test('a new account sees the first-project empty state and create action', function (): void {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->get(route('projects.index'));

    $response->assertOk()
        ->assertSee('Create your first project to start organizing work.')
        ->assertSee('New project')
        ->assertSee(route('projects.create', absolute: false), false);
});

test('the projects console exposes labelled navigation and keyboard-relevant controls', function (): void {
    $owner = User::factory()->create();
    Project::factory()->for($owner)->planned()->create(['name' => 'Keyboard Ready', 'key' => 'KEY']);

    $response = $this->actingAs($owner)->get(route('projects.index'));

    $response->assertOk()
        ->assertSee('<nav', false)
        ->assertSee('aria-expanded', false)
        ->assertSee('aria-controls', false)
        ->assertSee('Menu')
        ->assertSee('Projects')
        ->assertSee('PLANNED')
        ->assertSee('New project')
        ->assertSee('Find a project')
        ->assertSee('Open project');
});
