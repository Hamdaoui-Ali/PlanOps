<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Dashboard\Queries\DashboardQueryService;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('dashboard separates current state metrics from period activity metrics', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->active()->create();
    $active = Task::factory()->forProject($project)->active()->create(['due_on' => '2026-08-28']);
    $done = Task::factory()->forProject($project)->done()->create();
    Task::factory()->forProject($project)->cancelled()->create();
    TaskActivity::factory()->forTask($active)->create([
        'event_type' => TaskActivityType::TASK_CREATED,
        'created_at' => CarbonImmutable::parse('2026-08-29 10:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($done)->create([
        'event_type' => TaskActivityType::STATUS_CHANGED,
        'field' => 'status',
        'new_value' => ['status' => TaskStatus::DONE->value],
        'created_at' => CarbonImmutable::parse('2026-08-29 11:30:00 UTC'),
    ]);
    $period = new ReportPeriod('Today', CarbonImmutable::parse('2026-08-29 00:00:00 UTC'), CarbonImmutable::parse('2026-08-30 00:00:00 UTC'), 'day');

    $snapshot = (new DashboardQueryService)->for($owner, $period);

    expect($snapshot->activeProjects)->toBe(1)
        ->and($snapshot->statusCounts[TaskStatus::IN_PROGRESS->value])->toBe(1)
        ->and($snapshot->statusCounts[TaskStatus::CANCELLED->value] ?? 0)->toBe(0)
        ->and($snapshot->overdueCount)->toBe(1)
        ->and($snapshot->period['created'])->toBe(1)
        ->and($snapshot->period['completed'])->toBe(1)
        ->and($snapshot->period['balance'])->toBe(0);
});

test('dashboard metrics never include another users or deleted task data', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->active()->create();
    $foreignProject = Project::factory()->for($other)->active()->create();
    $owned = Task::factory()->forProject($project)->active()->create();
    $deleted = Task::factory()->forProject($project)->active()->create();
    $deleted->delete();
    $foreign = Task::factory()->forProject($foreignProject)->active()->create();
    foreach ([$deleted, $foreign] as $task) {
        TaskActivity::factory()->forTask($task)->create(['event_type' => TaskActivityType::TASK_CREATED]);
    }
    $period = new ReportPeriod('Today', CarbonImmutable::now()->startOfDay(), CarbonImmutable::now()->addDay()->startOfDay(), 'day');

    $snapshot = (new DashboardQueryService)->for($owner, $period);

    expect($snapshot->statusCounts[TaskStatus::IN_PROGRESS->value])->toBe(1)
        ->and($snapshot->period['created'])->toBe(0);
});
