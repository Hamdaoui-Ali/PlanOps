<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Analytics\Queries\TeamAnalyticsQuery;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Identity\Models\UserPreference;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function teamAnalyticsProject(User $owner): Project
{
    $project = Project::factory()->active()->create([
        'user_id' => $owner->id,
        'owner_id' => $owner->id,
    ]);
    ProjectMembership::factory()->owner()->create([
        'project_id' => $project->id,
        'user_id' => $owner->id,
    ]);

    return $project;
}

function teamAnalyticsPeriod(string $start = '2026-09-07 00:00:00 UTC', string $end = '2026-09-14 00:00:00 UTC'): ReportPeriod
{
    return new ReportPeriod('Selected period', CarbonImmutable::parse($start), CarbonImmutable::parse($end), 'week');
}

function recordTeamAnalyticsActivity(Task $task, TaskActivityType $eventType, string $createdAt, ?array $oldValue = null, ?array $newValue = null): void
{
    TaskActivity::factory()->forTask($task)->create([
        'event_type' => $eventType,
        'field' => $eventType === TaskActivityType::STATUS_CHANGED ? 'status' : null,
        'old_value' => $oldValue,
        'new_value' => $newValue,
        'created_at' => $createdAt,
    ]);
}

test('counts distinct project flow and current aggregate workload', function (): void {
    $owner = User::factory()->create();
    UserPreference::factory()->for($owner)->timezone('UTC')->create();
    $project = teamAnalyticsProject($owner);
    $period = teamAnalyticsPeriod();

    $active = Task::factory()->forProject($project)->active()->create([
        'created_at' => '2026-09-08 10:00:00 UTC',
        'assignee_id' => $owner->id,
    ]);
    $blocked = Task::factory()->forProject($project)->blocked()->create([
        'created_at' => '2026-09-09 10:00:00 UTC',
        'due_on' => '2026-09-27',
        'assignee_id' => $owner->id,
    ]);
    $done = Task::factory()->forProject($project)->done()->create([
        'created_at' => '2026-09-10 10:00:00 UTC',
        'completed_at' => '2026-09-11 10:00:00 UTC',
        'assignee_id' => $owner->id,
    ]);
    $reopened = Task::factory()->forProject($project)->active()->create([
        'created_at' => '2026-09-11 10:00:00 UTC',
        'assignee_id' => $owner->id,
    ]);
    $unassigned = Task::factory()->forProject($project)->create([
        'created_at' => '2026-09-12 10:00:00 UTC',
        'status' => TaskStatus::NOT_STARTED,
        'assignee_id' => null,
    ]);

    recordTeamAnalyticsActivity($active, TaskActivityType::TASK_CREATED, '2026-09-08 10:00:00 UTC');
    recordTeamAnalyticsActivity($active, TaskActivityType::STATUS_CHANGED, '2026-09-08 11:00:00 UTC', ['status' => TaskStatus::NOT_STARTED->value], ['status' => TaskStatus::IN_PROGRESS->value]);
    recordTeamAnalyticsActivity($active, TaskActivityType::STATUS_CHANGED, '2026-09-08 12:00:00 UTC', ['status' => TaskStatus::NOT_STARTED->value], ['status' => TaskStatus::IN_PROGRESS->value]);
    recordTeamAnalyticsActivity($blocked, TaskActivityType::TASK_CREATED, '2026-09-09 10:00:00 UTC');
    recordTeamAnalyticsActivity($blocked, TaskActivityType::STATUS_CHANGED, '2026-09-09 11:00:00 UTC', ['status' => TaskStatus::IN_PROGRESS->value], ['status' => TaskStatus::BLOCKED->value]);
    recordTeamAnalyticsActivity($done, TaskActivityType::TASK_CREATED, '2026-09-10 10:00:00 UTC');
    recordTeamAnalyticsActivity($done, TaskActivityType::STATUS_CHANGED, '2026-09-11 10:00:00 UTC', ['status' => TaskStatus::IN_REVIEW->value], ['status' => TaskStatus::DONE->value]);
    recordTeamAnalyticsActivity($done, TaskActivityType::STATUS_CHANGED, '2026-09-11 11:00:00 UTC', ['status' => TaskStatus::IN_REVIEW->value], ['status' => TaskStatus::DONE->value]);
    recordTeamAnalyticsActivity($reopened, TaskActivityType::TASK_CREATED, '2026-09-11 10:00:00 UTC');
    recordTeamAnalyticsActivity($reopened, TaskActivityType::STATUS_CHANGED, '2026-09-12 10:00:00 UTC', ['status' => TaskStatus::DONE->value], ['status' => TaskStatus::IN_PROGRESS->value]);
    recordTeamAnalyticsActivity($unassigned, TaskActivityType::TASK_CREATED, '2026-09-12 10:00:00 UTC');

    $snapshot = (new TeamAnalyticsQuery)->for($owner, $project, $period, CarbonImmutable::parse('2026-09-28 12:00:00 UTC'));

    expect($snapshot->throughput)->toBe([
        'created' => 5,
        'completed' => 1,
        'started' => 2,
        'reviewed' => 0,
        'blocked' => 1,
        'reopened' => 1,
    ])
        ->and($snapshot->statusDistribution)->toBe([
            TaskStatus::BACKLOG->value => 0,
            TaskStatus::NOT_STARTED->value => 1,
            TaskStatus::IN_PROGRESS->value => 2,
            TaskStatus::IN_REVIEW->value => 0,
            TaskStatus::BLOCKED->value => 1,
            TaskStatus::DONE->value => 1,
        ])
        ->and($snapshot->currentWorkload)->toBe([
            'active' => 2,
            'blocked' => 1,
            'overdue' => 1,
            'unassigned' => 1,
        ])
        ->and($snapshot->weeklyFlow->all())->toBe([
            ['label' => 'Sep 7, 2026', 'created' => 5, 'completed' => 1, 'blocked' => 1],
        ])
        ->and($snapshot->hasRecordedMovement)->toBeTrue();
});

test('excludes subtasks deleted tasks terminal attention and foreign project data', function (): void {
    $owner = User::factory()->create();
    UserPreference::factory()->for($owner)->timezone('UTC')->create();
    $project = teamAnalyticsProject($owner);
    $foreign = teamAnalyticsProject(User::factory()->create());
    $period = teamAnalyticsPeriod();

    $included = Task::factory()->forProject($project)->create([
        'created_at' => '2026-09-08 10:00:00 UTC',
        'status' => TaskStatus::NOT_STARTED,
        'assignee_id' => null,
    ]);
    $child = Task::factory()->forProject($project)->withParent($included)->create([
        'created_at' => '2026-09-08 11:00:00 UTC',
        'status' => TaskStatus::IN_PROGRESS,
    ]);
    $deleted = Task::factory()->forProject($project)->create([
        'created_at' => '2026-09-08 12:00:00 UTC',
        'status' => TaskStatus::IN_PROGRESS,
        'assignee_id' => $owner->id,
    ]);
    $deleted->delete();
    $done = Task::factory()->forProject($project)->done()->create([
        'created_at' => '2026-09-08 13:00:00 UTC',
        'assignee_id' => null,
        'due_on' => '2026-09-01',
    ]);
    $cancelled = Task::factory()->forProject($project)->cancelled()->create([
        'created_at' => '2026-09-08 14:00:00 UTC',
        'assignee_id' => null,
        'due_on' => '2026-09-01',
    ]);
    $foreignTask = Task::factory()->forProject($foreign)->create([
        'created_at' => '2026-09-08 15:00:00 UTC',
        'status' => TaskStatus::IN_PROGRESS,
    ]);

    foreach ([$included, $child, $deleted, $done, $cancelled, $foreignTask] as $task) {
        recordTeamAnalyticsActivity($task, TaskActivityType::TASK_CREATED, '2026-09-08 16:00:00 UTC');
    }

    $snapshot = (new TeamAnalyticsQuery)->for($owner, $project, $period, CarbonImmutable::parse('2026-09-28 12:00:00 UTC'));

    expect($snapshot->throughput['created'])->toBe(3)
        ->and($snapshot->statusDistribution)->toBe([
            TaskStatus::BACKLOG->value => 0,
            TaskStatus::NOT_STARTED->value => 1,
            TaskStatus::IN_PROGRESS->value => 0,
            TaskStatus::IN_REVIEW->value => 0,
            TaskStatus::BLOCKED->value => 0,
            TaskStatus::DONE->value => 1,
        ])
        ->and($snapshot->currentWorkload)->toBe([
            'active' => 0,
            'blocked' => 0,
            'overdue' => 0,
            'unassigned' => 1,
        ])
        ->and($snapshot->hasRecordedMovement)->toBeTrue();
});

test('buckets activity by the viewer timezone and week start preference', function (): void {
    $owner = User::factory()->create();
    UserPreference::factory()->for($owner)->timezone('America/New_York')->sundayStart()->create();
    $project = teamAnalyticsProject($owner);
    $period = teamAnalyticsPeriod('2026-09-06 04:00:00 UTC', '2026-09-20 04:00:00 UTC');
    $first = Task::factory()->forProject($project)->create(['created_at' => '2026-09-13 03:30:00 UTC']);
    $second = Task::factory()->forProject($project)->create(['created_at' => '2026-09-13 04:30:00 UTC']);

    recordTeamAnalyticsActivity($first, TaskActivityType::TASK_CREATED, '2026-09-13 03:30:00 UTC');
    recordTeamAnalyticsActivity($second, TaskActivityType::TASK_CREATED, '2026-09-13 04:30:00 UTC');

    $snapshot = (new TeamAnalyticsQuery)->for($owner, $project, $period, CarbonImmutable::parse('2026-09-20 12:00:00 UTC'));

    expect($snapshot->weeklyFlow->all())->toBe([
        ['label' => 'Sep 6, 2026', 'created' => 1, 'completed' => 0, 'blocked' => 0],
        ['label' => 'Sep 13, 2026', 'created' => 1, 'completed' => 0, 'blocked' => 0],
    ]);
});
