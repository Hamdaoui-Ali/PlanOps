<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('real task creation allocates distinct sequential numbers and advances the project counter', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN', 'next_task_number' => 1]);
    $connection = DB::connection($project->getConnectionName());

    $first = (new CreateTask)->handle($owner, $project, ['title' => 'Concurrent allocation one']);
    $second = (new CreateTask)->handle($owner, $project, ['title' => 'Concurrent allocation two']);
    $numbers = Task::query()
        ->where('user_id', $owner->id)
        ->where('project_id', $project->id)
        ->orderBy('number')
        ->pluck('number')
        ->all();
    $duplicateCount = Task::query()
        ->select('project_id', 'number')
        ->where('user_id', $owner->id)
        ->where('project_id', $project->id)
        ->groupBy('project_id', 'number')
        ->havingRaw('COUNT(*) > 1')
        ->count();

    expect($connection->getDriverName())->toBeIn(['sqlite', 'pgsql'])
        ->and($first->user_id)->toBe($owner->id)
        ->and($first->project_id)->toBe($project->id)
        ->and($second->user_id)->toBe($owner->id)
        ->and($second->project_id)->toBe($project->id)
        ->and($numbers)->toBe([1, 2])
        ->and(array_unique($numbers))->toHaveCount(2)
        ->and($project->fresh()->next_task_number)->toBe(3)
        ->and($duplicateCount)->toBe(0);
});
