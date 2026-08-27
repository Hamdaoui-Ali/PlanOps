<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function projectConsoleXPath(string $html): DOMXPath
{
    $document = new DOMDocument;

    @$document->loadHTML($html);

    return new DOMXPath($document);
}

test('the projects index renders the active owner ledger with project details and actions', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $active = Project::factory()->for($owner)->active()->create([
        'name' => 'Website Refresh',
        'key' => 'WEB',
        'target_on' => now()->addDays(21)->toDateString(),
    ]);
    $zeroEligible = Project::factory()->for($owner)->active()->create([
        'name' => 'Unscoped Initiative',
        'key' => 'ZERO',
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
        ->assertSee($active->target_on->format('M j, Y'))
        ->assertSee($zeroEligible->name)
        ->assertSee('No active scope')
        ->assertSee('0%')
        ->assertSee('New project')
        ->assertSee('Find a project')
        ->assertDontSee($archived->name)
        ->assertDontSee($foreign->name);

    $xpath = projectConsoleXPath($response->getContent());

    expect($xpath->query(sprintf(
        '//a[@href="%s" and normalize-space()="Open project"]',
        route('projects.edit', $active, absolute: false),
    ))->length)->toBe(1);
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
    $foreignArchived = Project::factory()->for(User::factory()->create())->create([
        'name' => 'Foreign Archived Discovery',
        'key' => 'FARC',
        'archived_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('projects.index', ['archived' => 'archived']));

    $response->assertOk()
        ->assertSee($archived->name)
        ->assertSee('ON HOLD')
        ->assertDontSee($active->name)
        ->assertDontSee($foreignArchived->name);
});

test('the projects console exposes all projects and preserves the submitted GET controls', function (): void {
    $owner = User::factory()->create();
    $active = Project::factory()->for($owner)->active()->create([
        'name' => 'All Hands Active',
        'key' => 'AHA',
    ]);
    $archived = Project::factory()->for($owner)->active()->create([
        'name' => 'All Hands Archived',
        'key' => 'AHR',
        'archived_at' => now(),
    ]);
    $filters = [
        'search' => 'All Hands',
        'status' => 'ACTIVE',
        'archived' => 'all',
        'target_date' => 'no_target',
        'sort' => 'name',
    ];

    $response = $this->actingAs($owner)->get(route('projects.index', $filters));

    $response->assertOk()
        ->assertSee($active->name)
        ->assertSee($archived->name);

    $xpath = projectConsoleXPath($response->getContent());

    expect($xpath->query('//form[translate(@method, "GET", "get") = "get"]')->length)->toBeGreaterThan(0);
    expect($xpath->query('//form[translate(@method, "GET", "get") = "get"]//input[@name="search" and @value="All Hands"]')->length)->toBe(1);
    expect($xpath->query('//form[translate(@method, "GET", "get") = "get"]//select[@name="status"]//option[@value="ACTIVE" and @selected]')->length)->toBe(1);
    expect($xpath->query('//form[translate(@method, "GET", "get") = "get"]//select[@name="archived"]//option[@value="all" and @selected]')->length)->toBe(1);
    expect($xpath->query('//form[translate(@method, "GET", "get") = "get"]//select[@name="target_date"]//option[@value="no_target" and @selected]')->length)->toBe(1);
    expect($xpath->query('//form[translate(@method, "GET", "get") = "get"]//select[@name="sort"]//option[@value="name" and @selected]')->length)->toBe(1);

    $allProjectsLinks = $xpath->query('//a[contains(@href, "archived=all")]');

    expect($allProjectsLinks->length)->toBeGreaterThan(0);

    foreach ($allProjectsLinks as $link) {
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);

        expect($query)->toMatchArray($filters);
    }
});

test('filtered projects with no matches explain the result and offer a reset path', function (): void {
    $owner = User::factory()->create();
    Project::factory()->for($owner)->active()->create(['name' => 'Existing Delivery', 'key' => 'LIVE']);

    $response = $this->actingAs($owner)->get(route('projects.index', ['search' => 'no-match']));

    $response->assertOk()
        ->assertSee('No projects match your current filters.')
        ->assertSee('Reset filters');

    $xpath = projectConsoleXPath($response->getContent());

    expect($xpath->query(sprintf(
        '//a[@href="%s" and normalize-space()="Reset filters"]',
        route('projects.index', absolute: false),
    ))->length)->toBe(1);
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
        ->assertSee('Projects')
        ->assertSee('PLANNED')
        ->assertSee('New project')
        ->assertSee('Find a project');

    $xpath = projectConsoleXPath($response->getContent());
    $menuButtons = $xpath->query(
        '//button[@aria-expanded and @aria-controls and (@aria-label="Menu" or normalize-space()="Menu")]',
    );

    expect($menuButtons->length)->toBe(1);

    $menuId = $menuButtons->item(0)->getAttribute('aria-controls');

    expect($xpath->query(sprintf(
        '//nav[@id="%s" and string-length(normalize-space(@aria-label)) > 0]',
        $menuId,
    ))->length)->toBe(1);
});
