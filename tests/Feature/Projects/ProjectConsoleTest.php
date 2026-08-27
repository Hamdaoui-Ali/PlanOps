<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;

uses(RefreshDatabase::class);

function projectConsoleXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    libxml_clear_errors();

    try {
        $loaded = $document->loadHTML($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }

    if (! $loaded) {
        throw new RuntimeException('Projects console response could not be parsed as HTML.');
    }

    return new DOMXPath($document);
}

function projectConsoleXPathLiteral(string $value): string
{
    if (! str_contains($value, "'")) {
        return "'{$value}'";
    }

    return 'concat(\''.str_replace("'", "', \"'\", '", $value).'\')';
}

function projectConsoleQueryParameters(DOMElement $element): array
{
    parse_str(parse_url($element->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);

    return $query;
}

function projectConsoleHasAccessibleName(DOMXPath $xpath, DOMElement $element): bool
{
    if (trim($element->getAttribute('aria-label')) !== '') {
        return true;
    }

    $labelledBy = trim($element->getAttribute('aria-labelledby'));

    if ($labelledBy === '') {
        return false;
    }

    foreach (preg_split('/\s+/', $labelledBy) as $id) {
        $label = $xpath->query('//*[@id='.projectConsoleXPathLiteral($id).']')->item(0);

        if ($label instanceof DOMElement && trim($label->textContent) !== '') {
            return true;
        }
    }

    return false;
}

function projectConsoleControlHasLabel(DOMXPath $xpath, DOMElement $control, string $label): bool
{
    if (trim($control->getAttribute('aria-label')) === $label || trim($control->textContent) === $label) {
        return true;
    }

    $id = trim($control->getAttribute('id'));

    return $id !== '' && $xpath->query('//label[@for='.projectConsoleXPathLiteral($id).' and normalize-space()='.projectConsoleXPathLiteral($label).']')->length > 0;
}

test('the projects index renders the active owner ledger with project details and actions', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $active = Project::factory()->for($owner)->active()->create([
        'name' => 'Website Refresh', 'key' => 'WEB', 'target_on' => now()->addDays(21)->toDateString(),
    ]);
    $zeroEligible = Project::factory()->for($owner)->active()->create(['name' => 'Unscoped Initiative', 'key' => 'ZERO']);
    $archived = Project::factory()->for($owner)->create(['name' => 'Retired Migration', 'key' => 'OLD', 'archived_at' => now()]);
    $foreign = Project::factory()->for($other)->active()->create(['name' => 'Other Team Project', 'key' => 'OTHER']);
    Task::factory()->forProject($active)->done()->create();
    Task::factory()->forProject($active)->create(['status' => TaskStatus::IN_PROGRESS]);

    $response = $this->actingAs($owner)->get(route('projects.index'));

    $response->assertOk()->assertSee('Website Refresh')->assertSee('WEB')->assertSee('ACTIVE')->assertSee('1 of 2 done')
        ->assertSee('50%')->assertSee($active->target_on->format('M j, Y'))->assertSee($zeroEligible->name)
        ->assertSee('No active scope')->assertSee('0%')->assertSee('New project')->assertSee('Find a project')
        ->assertDontSee($archived->name)->assertDontSee($foreign->name);

    $xpath = projectConsoleXPath($response->getContent());

    expect($xpath->query(sprintf('//a[@href=%s and normalize-space()="Open project"]', projectConsoleXPathLiteral(route('projects.edit', $active, absolute: false))))->length)->toBe(1);
});

test('the projects console query results honor search and status filters', function (): void {
    $owner = User::factory()->create();
    $matchedByName = Project::factory()->for($owner)->active()->create(['name' => 'Needle Delivery', 'key' => 'NDEL']);
    $matchedByKey = Project::factory()->for($owner)->planned()->create(['name' => 'Unrelated Initiative', 'key' => 'NEEDLE']);
    $wrongStatus = Project::factory()->for($owner)->planned()->create(['name' => 'Needle Planning', 'key' => 'NPLAN']);
    $wrongSearch = Project::factory()->for($owner)->active()->create(['name' => 'Haystack Delivery', 'key' => 'HAY']);

    $searchResponse = $this->actingAs($owner)->get(route('projects.index', ['search' => 'needle']));
    $searchResponse->assertOk()->assertSee($matchedByName->name)->assertSee($matchedByKey->name)->assertSee($wrongStatus->name)->assertDontSee($wrongSearch->name);

    $statusResponse = $this->actingAs($owner)->get(route('projects.index', ['status' => 'ACTIVE']));
    $statusResponse->assertOk()->assertSee($matchedByName->name)->assertSee($wrongSearch->name)->assertDontSee($matchedByKey->name)->assertDontSee($wrongStatus->name);
});

test('the archived projects filter exposes only the owners archived projects', function (): void {
    $owner = User::factory()->create();
    $archived = Project::factory()->for($owner)->onHold()->create(['name' => 'Archived Discovery', 'key' => 'ARC', 'archived_at' => now()]);
    $active = Project::factory()->for($owner)->active()->create(['name' => 'Active Delivery', 'key' => 'LIVE']);
    $foreignArchived = Project::factory()->for(User::factory()->create())->create(['name' => 'Foreign Archived Discovery', 'key' => 'FARC', 'archived_at' => now()]);

    $response = $this->actingAs($owner)->get(route('projects.index', ['archived' => 'archived']));

    $response->assertOk()->assertSee($archived->name)->assertSee('ON HOLD')->assertDontSee($active->name)->assertDontSee($foreignArchived->name);
});

test('the projects console query results honor overdue and no-target filters', function (): void {
    $owner = User::factory()->create();
    $overdue = Project::factory()->for($owner)->active()->create(['name' => 'Overdue Delivery', 'key' => 'LATE', 'target_on' => now()->subDay()->toDateString()]);
    $today = Project::factory()->for($owner)->active()->create(['name' => 'Due Today Delivery', 'key' => 'TODAY', 'target_on' => today()->toDateString()]);
    $noTarget = Project::factory()->for($owner)->active()->create(['name' => 'Unscheduled Delivery', 'key' => 'NONE', 'target_on' => null]);

    $overdueResponse = $this->actingAs($owner)->get(route('projects.index', ['target_date' => 'overdue']));
    $overdueResponse->assertOk()->assertSee($overdue->name)->assertDontSee($today->name)->assertDontSee($noTarget->name);

    $noTargetResponse = $this->actingAs($owner)->get(route('projects.index', ['target_date' => 'no_target']));
    $noTargetResponse->assertOk()->assertSee($noTarget->name)->assertDontSee($overdue->name)->assertDontSee($today->name);
});

test('the projects console query results honor name sorting', function (): void {
    $owner = User::factory()->create();
    $alpha = Project::factory()->for($owner)->active()->create(['name' => 'Alpha Sort Project', 'key' => 'ALPHA']);
    $middle = Project::factory()->for($owner)->active()->create(['name' => 'Middle Sort Project', 'key' => 'MID']);
    $zulu = Project::factory()->for($owner)->active()->create(['name' => 'Zulu Sort Project', 'key' => 'ZULU']);

    $response = $this->actingAs($owner)->get(route('projects.index', ['sort' => 'name']));

    $response->assertOk()->assertSeeInOrder([$alpha->name, $middle->name, $zulu->name]);
});

test('the projects console exposes archived view controls that preserve active filters', function (): void {
    $owner = User::factory()->create();
    $active = Project::factory()->for($owner)->active()->create(['name' => 'All Hands Active', 'key' => 'AHA']);
    $archived = Project::factory()->for($owner)->active()->create(['name' => 'All Hands Archived', 'key' => 'AHR', 'archived_at' => now()]);
    $filters = ['search' => 'All Hands', 'status' => 'ACTIVE', 'archived' => 'all', 'target_date' => 'no_target', 'sort' => 'name'];

    $response = $this->actingAs($owner)->get(route('projects.index', $filters));
    $response->assertOk()->assertSee($active->name)->assertSee($archived->name);

    $xpath = projectConsoleXPath($response->getContent());
    $preservedFilters = Arr::except($filters, 'archived');

    foreach (['Active' => 'active', 'Archived' => 'archived', 'All' => 'all'] as $label => $archivedValue) {
        $links = $xpath->query('//a[normalize-space()='.projectConsoleXPathLiteral($label).']');
        $controls = $xpath->query('//*[@name="archived" and @value='.projectConsoleXPathLiteral($archivedValue).']');

        expect($links->length + $controls->length)->toBeGreaterThan(0);

        foreach ($links as $link) {
            expect(projectConsoleQueryParameters($link))->toMatchArray([...$preservedFilters, 'archived' => $archivedValue]);
        }

        foreach ($controls as $control) {
            $form = $xpath->query('ancestor::form[translate(@method, "GET", "get") = "get"]', $control)->item(0);

            expect(projectConsoleControlHasLabel($xpath, $control, $label))->toBeTrue();
            expect($form)->toBeInstanceOf(DOMElement::class);
            expect($form->getAttribute('action'))->toBeIn(['', route('projects.index', absolute: false)]);

            foreach ($preservedFilters as $name => $value) {
                expect($xpath->query('.//*[@name='.projectConsoleXPathLiteral($name).' and (@value='.projectConsoleXPathLiteral($value).' or .//option[@value='.projectConsoleXPathLiteral($value).' and @selected])]', $form)->length)->toBeGreaterThan(0);
            }
        }
    }
});

test('the projects console paginates filtered results and retains the query string', function (): void {
    $owner = User::factory()->create();
    $filters = ['search' => 'Pagination Project', 'status' => 'ACTIVE', 'archived' => 'active', 'target_date' => 'no_target', 'sort' => 'name'];

    Project::factory()->count(51)->for($owner)->active()->sequence(
        fn ($sequence): array => ['name' => sprintf('Pagination Project %03d', $sequence->index + 1), 'key' => sprintf('PG%03d', $sequence->index + 1), 'target_on' => null],
    )->create();

    $response = $this->actingAs($owner)->get(route('projects.index', $filters));
    $response->assertOk()->assertSee('Pagination Project 001');

    $xpath = projectConsoleXPath($response->getContent());
    $pageTwoLinks = $xpath->query('//a[contains(@href, "page=2") or contains(@href, "page%3D2")]');
    expect($pageTwoLinks->length)->toBeGreaterThan(0);

    foreach ($pageTwoLinks as $link) {
        expect(projectConsoleQueryParameters($link))->toMatchArray([...$filters, 'page' => '2']);
    }
});

test('filtered projects with no matches explain the result and offer a reset path', function (): void {
    $owner = User::factory()->create();
    Project::factory()->for($owner)->active()->create(['name' => 'Existing Delivery', 'key' => 'LIVE']);

    $response = $this->actingAs($owner)->get(route('projects.index', ['search' => 'no-match']));
    $response->assertOk()->assertSee('No projects match your current filters.')->assertSee('Reset filters');

    $xpath = projectConsoleXPath($response->getContent());
    expect($xpath->query(sprintf('//a[@href=%s and normalize-space()="Reset filters"]', projectConsoleXPathLiteral(route('projects.index', absolute: false))))->length)->toBe(1);
});

test('a new account sees the first-project empty state and create action', function (): void {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->get(route('projects.index'));

    $response->assertOk()->assertSee('Create your first project to start organizing work.')->assertSee('New project')->assertSee(route('projects.create', absolute: false), false);
});

test('the projects console associates every generic filter control with a label', function (): void {
    $owner = User::factory()->create();
    Project::factory()->for($owner)->planned()->create(['name' => 'Label Ready', 'key' => 'LABEL']);

    $response = $this->actingAs($owner)->get(route('projects.index'));
    $response->assertOk();

    $xpath = projectConsoleXPath($response->getContent());

    foreach (['search', 'status', 'target_date', 'sort'] as $name) {
        $controls = $xpath->query('//*[self::input or self::select][@name='.projectConsoleXPathLiteral($name).']');
        expect($controls->length)->toBeGreaterThan(0);

        foreach ($controls as $control) {
            $id = trim($control->getAttribute('id'));
            expect($id)->not->toBe('');
            expect($xpath->query('//label[@for='.projectConsoleXPathLiteral($id).']')->length)->toBeGreaterThan(0);
        }
    }
});

test('the projects console exposes labelled navigation and keyboard-relevant controls', function (): void {
    $owner = User::factory()->create();
    Project::factory()->for($owner)->planned()->create(['name' => 'Keyboard Ready', 'key' => 'KEY']);

    $response = $this->actingAs($owner)->get(route('projects.index'));
    $response->assertOk()->assertSee('Projects')->assertSee('PLANNED')->assertSee('New project')->assertSee('Find a project');

    $xpath = projectConsoleXPath($response->getContent());
    $menuButtons = $xpath->query('//button[@aria-expanded and @aria-controls]');
    expect($menuButtons->length)->toBeGreaterThan(0);

    foreach ($menuButtons as $menuButton) {
        expect(trim($menuButton->getAttribute('aria-label')) !== '' || trim($menuButton->textContent) !== '')->toBeTrue();

        $menu = $xpath->query('//nav[@id='.projectConsoleXPathLiteral(trim($menuButton->getAttribute('aria-controls'))).']')->item(0);
        expect($menu)->toBeInstanceOf(DOMElement::class);
        expect(projectConsoleHasAccessibleName($xpath, $menu))->toBeTrue();
    }
});
