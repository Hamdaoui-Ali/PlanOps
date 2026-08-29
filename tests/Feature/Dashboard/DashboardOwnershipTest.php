<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard renders only the authenticated users current work', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownedProject = Project::factory()->for($owner)->active()->create();
    $foreignProject = Project::factory()->for($other)->active()->create();
    Task::factory()->forProject($ownedProject)->active()->create();
    Task::factory()->forProject($foreignProject)->active()->create();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Active Projects')
        ->assertSee('In Progress');
});
