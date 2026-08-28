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

test('PostgreSQL serializes independent task creation attempts through the project row lock', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN', 'next_task_number' => 1]);
    $connection = DB::connection($project->getConnectionName());

    if ($connection->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL is required to verify the independent-connection row-lock path.');
    }

    expect(function_exists('pcntl_fork'))->toBeTrue();

    $pidPath = tempnam(sys_get_temp_dir(), 'planops-task-pid-');
    $resultPath = tempnam(sys_get_temp_dir(), 'planops-task-result-');
    $childProcessId = null;
    $childBackendId = null;
    $childWaitedForProjectLock = false;
    $first = null;
    $waitStatus = 0;

    try {
        DB::connection($project->getConnectionName())->transaction(function () use ($owner, $project, $pidPath, $resultPath, &$childProcessId, &$childBackendId, &$childWaitedForProjectLock, &$first): void {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            $childProcessId = pcntl_fork();

            if ($childProcessId === 0) {
                DB::disconnect($project->getConnectionName());

                try {
                    $childConnection = DB::connection($project->getConnectionName());
                    $backend = $childConnection->selectOne('select pg_backend_pid() as pid');
                    file_put_contents($pidPath, (string) $backend->pid, LOCK_EX);

                    $childOwner = User::query()->findOrFail($owner->id);
                    $childProject = Project::query()->findOrFail($project->id);
                    $task = (new CreateTask)->handle($childOwner, $childProject, ['title' => 'Independent allocation']);

                    file_put_contents($resultPath, json_encode([
                        'id' => $task->id,
                        'user_id' => $task->user_id,
                        'project_id' => $task->project_id,
                        'number' => $task->number,
                    ], JSON_THROW_ON_ERROR), LOCK_EX);

                    exit(0);
                } catch (Throwable $exception) {
                    file_put_contents($resultPath, json_encode([
                        'exception' => $exception::class,
                        'message' => $exception->getMessage(),
                    ], JSON_THROW_ON_ERROR), LOCK_EX);

                    exit(1);
                }
            }

            expect($childProcessId)->toBeGreaterThan(0);

            $deadline = microtime(true) + 5;
            while (microtime(true) < $deadline && trim((string) file_get_contents($pidPath)) === '') {
                usleep(10_000);
            }

            $childBackendId = (int) trim((string) file_get_contents($pidPath));

            while (microtime(true) < $deadline) {
                $childWaitedForProjectLock = DB::connection($project->getConnectionName())
                    ->table('pg_locks')
                    ->where('pid', $childBackendId)
                    ->where('granted', false)
                    ->exists();

                if ($childWaitedForProjectLock) {
                    break;
                }

                usleep(10_000);
            }

            $first = (new CreateTask)->handle($owner, $lockedProject, ['title' => 'Locked allocation']);
        });

        pcntl_waitpid($childProcessId, $waitStatus);
        $childResult = json_decode((string) file_get_contents($resultPath), true, flags: JSON_THROW_ON_ERROR);
        $numbers = Task::query()
            ->where('user_id', $owner->id)
            ->where('project_id', $project->id)
            ->orderBy('number')
            ->pluck('number')
            ->all();

        expect($childBackendId)->toBeGreaterThan(0)
            ->and($childWaitedForProjectLock)->toBeTrue()
            ->and(pcntl_wexitstatus($waitStatus))->toBe(0)
            ->and($first)->not->toBeNull()
            ->and($first->user_id)->toBe($owner->id)
            ->and($first->project_id)->toBe($project->id)
            ->and($childResult)->toMatchArray([
                'user_id' => $owner->id,
                'project_id' => $project->id,
                'number' => 2,
            ])
            ->and($numbers)->toBe([1, 2])
            ->and($project->fresh()->next_task_number)->toBe(3);
    } finally {
        if (is_int($childProcessId) && $childProcessId > 0) {
            pcntl_waitpid($childProcessId, $waitStatus, WNOHANG);
        }

        @unlink($pidPath);
        @unlink($resultPath);
    }
});
