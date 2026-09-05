<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function runCollaborationBackfill(bool $dryRun = false): array
{
    $exitCode = Artisan::call('planops:collaboration-backfill', array_filter([
        '--chunk' => 2,
        '--dry-run' => $dryRun ? true : null,
        '--no-interaction' => true,
    ], fn (mixed $value): bool => $value !== null));

    expect($exitCode)->toBe(0);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}

test('collaboration backfill migrates legacy identity data and is idempotent', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => ' plan ']);
    $task = Task::factory()->forProject($project)->create(['assignee_id' => null]);
    $activity = TaskActivity::factory()->forTask($task)->create();
    $label = Label::factory()->forUser($owner)->create();
    $label->tasks()->attach($task);

    $firstReport = runCollaborationBackfill();
    $project->refresh();
    $task->refresh();
    $activity->refresh();
    $label->refresh();

    expect($project->key)->toBe('PLAN')
        ->and($project->owner_id)->toBe($owner->id)
        ->and(ProjectMembership::query()->where('project_id', $project->id)->where('user_id', $owner->id)->where('role', ProjectRole::OWNER->value)->whereNull('removed_at')->count())->toBe(1)
        ->and($task->created_by_user_id)->toBe($owner->id)
        ->and($task->assignee_id)->toBe($owner->id)
        ->and($activity->actor_user_id)->toBe($owner->id)
        ->and($label->project_id)->toBe($project->id)
        ->and($firstReport['projects_updated'])->toBe(1)
        ->and($firstReport['memberships_upserted'])->toBe(1);

    $snapshot = [
        'project' => $project->fresh()->only(['key', 'owner_id']),
        'task' => $task->fresh()->only(['created_by_user_id', 'assignee_id']),
        'activity' => $activity->fresh()->actor_user_id,
        'label' => $label->fresh()->project_id,
        'memberships' => ProjectMembership::query()->count(),
    ];
    $secondReport = runCollaborationBackfill();

    expect($secondReport['memberships_upserted'])->toBe(0)
        ->and([
            'project' => $project->fresh()->only(['key', 'owner_id']),
            'task' => $task->fresh()->only(['created_by_user_id', 'assignee_id']),
            'activity' => $activity->fresh()->actor_user_id,
            'label' => $label->fresh()->project_id,
            'memberships' => ProjectMembership::query()->count(),
        ])->toBe($snapshot);
});

test('collaboration backfill dry-run does not change legacy rows', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'plan']);
    $task = Task::factory()->forProject($project)->create();

    $before = [$project->key, $project->owner_id, $task->created_by_user_id, ProjectMembership::count()];
    $report = runCollaborationBackfill(dryRun: true);
    $after = [$project->fresh()->key, $project->fresh()->owner_id, $task->fresh()->created_by_user_id, ProjectMembership::count()];

    expect($report['dry_run'])->toBeTrue()
        ->and($report['projects_updated'])->toBe(1)
        ->and($before)->toBe($after);
});
