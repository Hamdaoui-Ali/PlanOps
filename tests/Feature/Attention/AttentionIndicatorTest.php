<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

it('shows overdue blocked long-review and stale suggestions without changing task state', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['key' => 'PLAN']);
    $overdue = Task::factory()->forProject($project)->create(['title' => 'Overdue', 'due_on' => '2026-08-29', 'status' => TaskStatus::NOT_STARTED]);
    $blocked = Task::factory()->forProject($project)->blocked()->create(['title' => 'Blocked']);
    $review = Task::factory()->forProject($project)->inReview()->create(['title' => 'Long review', 'status_changed_at' => now()->subDays(4)]);
    $stale = Task::factory()->forProject($project)->active()->create(['title' => 'Stale', 'updated_at' => now()->subDays(8)]);

    $response = $this->actingAs($user)->get(route('projects.show', $project));

    $response->assertOk()
        ->assertSee('Overdue')
        ->assertSee('Blocked')
        ->assertSee('Long review')
        ->assertSee('Stale')
        ->assertSee('Review this task')
        ->assertSee('Past due')
        ->assertSee('Blocked work')
        ->assertSee('In review for 4 days')
        ->assertSee('No status change for 8 days');
    expect($overdue->fresh()->status)->toBe(TaskStatus::NOT_STARTED)
        ->and($blocked->fresh()->status)->toBe(TaskStatus::BLOCKED)
        ->and($review->fresh()->status)->toBe(TaskStatus::IN_REVIEW)
        ->and($stale->fresh()->status)->toBe(TaskStatus::IN_PROGRESS);
    Carbon::setTestNow();
});

it('does not show attention suggestions for a foreign project', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');
    $user = User::factory()->create();
    $project = Project::factory()->for(User::factory())->create();
    Task::factory()->forProject($project)->blocked()->create(['title' => 'Secret blocked']);

    $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertDontSee('Secret blocked');
    Carbon::setTestNow();
});
