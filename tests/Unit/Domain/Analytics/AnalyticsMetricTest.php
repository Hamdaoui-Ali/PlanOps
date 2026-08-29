<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Analytics\Queries\AnalyticsQueryService;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        ->and($snapshot->cycleTimeMedianHours)->toBe(48.0);
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
