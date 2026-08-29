<?php

use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an owner can open global analytics with a selected period', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('analytics', ['period' => 'month']))
        ->assertOk()
        ->assertSee('Analytics')
        ->assertSee('Throughput')
        ->assertSee('Lead time')
        ->assertSee('Month');
});

test('an owner can open analytics for an owned project but not a foreign project', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['name' => 'Owned analytics']);
    $foreign = Project::factory()->for($other)->create();

    $this->actingAs($owner)->get(route('projects.analytics', $project))
        ->assertOk()
        ->assertSee('Owned analytics');

    $this->actingAs($owner)->get(route('projects.analytics', $foreign))->assertNotFound();
});
