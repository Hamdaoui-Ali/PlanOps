<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function collaborationPreflightReport(): array
{
    $exitCode = Artisan::call('planops:collaboration-preflight', ['--json' => true]);

    expect($exitCode)->toBe(0);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}

test('collaboration preflight reports a clean deterministic database', function (): void {
    expect(collaborationPreflightReport())->toBe([
        'missing_project_owners' => ['count' => 0, 'sample_ids' => []],
        'duplicate_project_keys' => ['count' => 0, 'sample_ids' => []],
        'duplicate_task_numbers' => ['count' => 0, 'sample_ids' => []],
        'orphaned_tasks' => ['count' => 0, 'sample_ids' => []],
        'orphaned_activities' => ['count' => 0, 'sample_ids' => []],
        'orphaned_labels' => ['count' => 0, 'sample_ids' => []],
        'duplicate_project_labels' => ['count' => 0, 'sample_ids' => []],
        'legacy_labels_spanning_projects' => ['count' => 0, 'sample_ids' => []],
        'invalid_assignees' => ['count' => 0, 'sample_ids' => []],
        'existing_activity_rows' => ['count' => 0, 'sample_ids' => []],
    ]);
});

test('collaboration preflight detects migration anomalies without writing', function (): void {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $firstProject = Project::factory()->for($firstOwner)->create(['key' => 'PLAN']);
    $secondProject = Project::factory()->for($secondOwner)->create(['key' => 'plan']);
    $firstTask = Task::factory()->forProject($firstProject)->create();
    $secondTask = Task::factory()->forProject($secondProject)->create();
    $label = Label::factory()->forUser($firstOwner)->create();
    $label->tasks()->attach([$firstTask->id, $secondTask->id]);
    $assignee = User::factory()->create();
    $assignedTask = Task::factory()->forProject($firstProject)->create(['assignee_id' => $assignee->id]);
    TaskActivity::factory()->forTask($firstTask)->create(['event_type' => TaskActivityType::TASK_UPDATED]);

    $before = [
        'projects' => Project::count(),
        'tasks' => Task::count(),
        'labels' => Label::count(),
        'activities' => TaskActivity::count(),
    ];
    $report = collaborationPreflightReport();
    $after = [
        'projects' => Project::count(),
        'tasks' => Task::count(),
        'labels' => Label::count(),
        'activities' => TaskActivity::count(),
    ];

    expect($report['missing_project_owners']['count'])->toBe(2)
        ->and($report['missing_project_owners']['sample_ids'])->toBe([$firstProject->id, $secondProject->id])
        ->and($report['duplicate_project_keys']['count'])->toBe(1)
        ->and($report['duplicate_project_keys']['sample_ids'])->toBe([$firstProject->id, $secondProject->id])
        ->and($report['legacy_labels_spanning_projects']['count'])->toBe(1)
        ->and($report['legacy_labels_spanning_projects']['sample_ids'])->toBe([$label->id])
        ->and($report['invalid_assignees']['count'])->toBe(1)
        ->and($report['invalid_assignees']['sample_ids'])->toBe([$assignedTask->id])
        ->and($report['existing_activity_rows']['count'])->toBe(1)
        ->and($report['existing_activity_rows']['sample_ids'])->toBe([$firstTask->id])
        ->and($before)->toBe($after);
});
