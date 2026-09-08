<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Analytics\Queries\AnalyticsQueryService;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('global analytics loads only projects where the viewer can see detailed reports', function (ProjectRole $role): void {
    $viewer = User::factory()->create();
    $allowed = Project::factory()->create(['name' => 'Allowed reporting']);
    $restricted = Project::factory()->create(['name' => 'Restricted reporting']);
    ProjectMembership::factory()->create(['project_id' => $allowed->id, 'user_id' => $viewer->id, 'role' => $role]);
    ProjectMembership::factory()->create(['project_id' => $restricted->id, 'user_id' => $viewer->id]);
    $allowedTask = Task::factory()->forProject($allowed)->done()->create();
    $restrictedTask = Task::factory()->forProject($restricted)->done()->create();
    foreach ([$allowedTask, $restrictedTask] as $task) {
        TaskActivity::factory()->forTask($task)->create([
            'event_type' => TaskActivityType::TASK_CREATED,
            'created_at' => '2026-08-02 00:00:00',
        ]);
    }
    $loadedTasks = [];
    $loadedActivityTasks = [];
    Task::retrieved(function (Task $task) use (&$loadedTasks): void {
        $loadedTasks[] = $task->id;
    });
    TaskActivity::retrieved(function (TaskActivity $activity) use (&$loadedActivityTasks): void {
        $loadedActivityTasks[] = $activity->task_id;
    });
    $period = new ReportPeriod('August', CarbonImmutable::parse('2026-08-01 UTC'), CarbonImmutable::parse('2026-09-01 UTC'), 'month');

    $snapshot = (new AnalyticsQueryService)->for($viewer, $period);

    expect($snapshot->throughput['created'])->toBe(1)
        ->and($snapshot->projectContribution->pluck('project_id')->all())->toBe([$allowed->id])
        ->and($snapshot->projectContribution->first()['eligible'])->toBe(1)
        ->and($snapshot->projectContribution->first()['completed'])->toBe(1)
        ->and(array_values(array_unique($loadedTasks)))->toBe([$allowedTask->id])
        ->and($loadedActivityTasks)->toBe([$allowedTask->id]);
})->with([ProjectRole::OWNER, ProjectRole::ADMIN]);

test('global analytics preserves legacy owners and excludes removed memberships even for legacy owner ids', function (): void {
    $viewer = User::factory()->create();
    $legacy = Project::factory()->for($viewer)->create();
    $removed = Project::factory()->for($viewer)->create();
    ProjectMembership::factory()->owner()->create([
        'project_id' => $removed->id, 'user_id' => $viewer->id, 'removed_at' => now(),
    ]);
    Task::factory()->forProject($legacy)->create();
    Task::factory()->forProject($removed)->create();
    $period = new ReportPeriod('August', CarbonImmutable::parse('2026-08-01 UTC'), CarbonImmutable::parse('2026-09-01 UTC'), 'month');

    expect((new AnalyticsQueryService)->for($viewer, $period)->projectContribution->pluck('project_id')->all())
        ->toBe([$legacy->id]);
});

test('analytics counts distinct lifecycle facts and calculates median durations', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->active()->create();
    $first = Task::factory()->forProject($project)->create([
        'created_at' => '2026-08-01 09:00:00',
        'first_started_at' => '2026-08-02 09:00:00',
        'status' => TaskStatus::DONE,
    ]);
    $second = Task::factory()->forProject($project)->create([
        'created_at' => '2026-08-03 09:00:00',
        'first_started_at' => '2026-08-04 09:00:00',
        'status' => TaskStatus::DONE,
    ]);
    foreach ([[$first, '2026-08-05 09:00:00'], [$second, '2026-08-07 09:00:00']] as [$task, $completedAt]) {
        TaskActivity::factory()->forTask($task)->create(['event_type' => TaskActivityType::TASK_CREATED, 'created_at' => $task->created_at]);
        TaskActivity::factory()->forTask($task)->create(['event_type' => TaskActivityType::STATUS_CHANGED, 'field' => 'status', 'old_value' => ['status' => 'IN_PROGRESS'], 'new_value' => ['status' => 'DONE'], 'created_at' => $completedAt]);
    }
    TaskActivity::factory()->forTask($first)->create(['event_type' => TaskActivityType::STATUS_CHANGED, 'field' => 'status', 'old_value' => ['status' => 'DONE'], 'new_value' => ['status' => 'IN_PROGRESS'], 'created_at' => '2026-08-08 09:00:00']);
    $period = new ReportPeriod('August', CarbonImmutable::parse('2026-08-01 00:00:00 UTC'), CarbonImmutable::parse('2026-09-01 00:00:00 UTC'), 'month');

    $snapshot = (new AnalyticsQueryService)->for($owner, $period);

    expect($snapshot->throughput['created'])->toBe(2)
        ->and($snapshot->throughput['completed'])->toBe(2)
        ->and($snapshot->throughput['reopened'])->toBe(1)
        ->and($snapshot->leadTimeMedianHours)->toBe(96.0)
        ->and($snapshot->cycleTimeMedianHours)->toBe(72.0);
});

test('analytics keeps project contribution owner-scoped and excludes subtasks', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->active()->create(['name' => 'Owned analytics']);
    $foreign = Project::factory()->for($other)->active()->create(['name' => 'Foreign analytics']);
    $parent = Task::factory()->forProject($project)->done()->create();
    Task::factory()->forProject($project)->withParent($parent)->done()->create();
    Task::factory()->forProject($foreign)->done()->create();
    $period = new ReportPeriod('August', CarbonImmutable::parse('2026-08-01 00:00:00 UTC'), CarbonImmutable::parse('2026-09-01 00:00:00 UTC'), 'month');

    $snapshot = (new AnalyticsQueryService)->for($owner, $period);

    expect($snapshot->projectContribution)->toHaveCount(1)
        ->and($snapshot->projectContribution->first()['project_id'])->toBe($project->id);
});
