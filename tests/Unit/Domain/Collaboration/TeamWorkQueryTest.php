<?php

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Collaboration\Queries\TeamWorkQuery;
use App\Domain\Identity\Models\UserPreference;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function teamWorkProject(User $owner): Project
{
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);

    return $project;
}

test('calculates top-level workload metrics without leaking other task data', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create(['name' => 'Admin User']);
    $member = User::factory()->create(['name' => 'Member User']);
    $project = teamWorkProject($owner);
    ProjectMembership::factory()->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);

    $now = CarbonImmutable::parse('2026-09-28 12:00:00 UTC');
    UserPreference::factory()->for($owner)->timezone('UTC')->sundayStart()->create();

    $active = Task::factory()->forProject($project)->active()->create(['assignee_id' => $member->id]);
    Task::factory()->forProject($project)->inReview()->create(['assignee_id' => $admin->id]);
    Task::factory()->forProject($project)->blocked()->create([
        'assignee_id' => $member->id,
        'due_on' => '2026-09-27',
    ]);
    Task::factory()->forProject($project)->done()->create([
        'assignee_id' => $member->id,
        'completed_at' => '2026-09-27 10:00:00',
    ]);
    Task::factory()->forProject($project)->done()->create([
        'assignee_id' => $member->id,
        'completed_at' => '2026-09-26 10:00:00',
    ]);
    Task::factory()->forProject($project)->create([
        'assignee_id' => null,
        'status' => TaskStatus::NOT_STARTED,
    ]);
    Task::factory()->forProject($project)->withParent($active)->active()->create(['assignee_id' => $member->id]);
    $deleted = Task::factory()->forProject($project)->active()->create(['assignee_id' => $member->id]);
    $deleted->delete();

    $foreignProject = teamWorkProject(User::factory()->create());
    Task::factory()->forProject($foreignProject)->active()->create(['assignee_id' => $member->id]);

    $snapshot = (new TeamWorkQuery)->for($owner, $project, $now);
    $memberRow = $snapshot->members->firstWhere('userId', $member->id);
    $adminRow = $snapshot->members->firstWhere('userId', $admin->id);

    expect($memberRow->role)->toBe(ProjectRole::MEMBER)
        ->and($memberRow->activeCount)->toBe(1)
        ->and($memberRow->blockedCount)->toBe(1)
        ->and($memberRow->overdueCount)->toBe(1)
        ->and($memberRow->completedThisWeekCount)->toBe(1)
        ->and($adminRow->activeCount)->toBe(1)
        ->and($adminRow->blockedCount)->toBe(0)
        ->and($snapshot->unassignedCount)->toBe(1)
        ->and($snapshot->weekStart)->toEqual(CarbonImmutable::parse('2026-09-27 00:00:00 UTC'))
        ->and($snapshot->weekEnd)->toEqual(CarbonImmutable::parse('2026-10-04 00:00:00 UTC'));
});

test('excludes removed members and keeps an empty member row visible', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Active Member']);
    $removed = User::factory()->create(['name' => 'Removed Member']);
    $project = teamWorkProject($owner);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    ProjectMembership::factory()->create([
        'project_id' => $project->id,
        'user_id' => $removed->id,
        'removed_at' => '2026-09-01 10:00:00',
    ]);
    Task::factory()->forProject($project)->active()->create(['assignee_id' => $removed->id]);

    $snapshot = (new TeamWorkQuery)->for($owner, $project, CarbonImmutable::parse('2026-09-28 12:00:00 UTC'));

    expect($snapshot->members->pluck('userId')->all())->toContain($owner->id, $member->id)
        ->and($snapshot->members->pluck('userId')->all())->not->toContain($removed->id)
        ->and($snapshot->members->firstWhere('userId', $member->id)->activeCount)->toBe(0);
});
