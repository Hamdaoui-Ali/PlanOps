<?php

use App\Domain\Collaboration\Actions\RemoveProjectMember;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serializes assignment against member removal on PostgreSQL using independent processes', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql' || ! function_exists('proc_open')) {
        $this->markTestSkipped('PostgreSQL and proc_open are required for the independent-process race test.');
    }

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'owner_id' => $owner->id]);
    ProjectMembership::factory()->owner()->create(['project_id' => $project->id, 'user_id' => $owner->id]);
    ProjectMembership::factory()->create(['project_id' => $project->id, 'user_id' => $member->id]);
    $task = Task::factory()->forProject($project)->create(['user_id' => $owner->id, 'assignee_id' => null]);

    $startedPath = tempnam(sys_get_temp_dir(), 'planops-assignment-started-');
    $resultPath = tempnam(sys_get_temp_dir(), 'planops-assignment-result-');
    $stdoutPath = tempnam(sys_get_temp_dir(), 'planops-assignment-stdout-');
    $stderrPath = tempnam(sys_get_temp_dir(), 'planops-assignment-stderr-');
    $process = null;
    $pipes = [];

    try {
        DB::beginTransaction();
        Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

        $command = [PHP_BINARY, base_path('tests/Support/assignment_race_worker.php'), $task->id, $owner->id, $member->id, $startedPath, $resultPath];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutPath, 'a'],
            2 => ['file', $stderrPath, 'a'],
        ], $pipes, base_path(), [
            'APP_ENV' => (string) getenv('APP_ENV'),
            'APP_KEY' => (string) getenv('APP_KEY'),
            'DB_CONNECTION' => (string) getenv('DB_CONNECTION'),
            'DB_HOST' => (string) getenv('DB_HOST'),
            'DB_PORT' => (string) getenv('DB_PORT'),
            'DB_DATABASE' => (string) getenv('DB_DATABASE'),
            'DB_USERNAME' => (string) getenv('DB_USERNAME'),
            'DB_PASSWORD' => (string) getenv('DB_PASSWORD'),
        ]);
        expect(is_resource($process))->toBeTrue();
        fclose($pipes[0]);
        $pipes = [];

        $deadline = microtime(true) + 5;
        while (trim((string) file_get_contents($startedPath)) !== 'started' && microtime(true) < $deadline) {
            usleep(10_000);
        }
        expect(trim((string) file_get_contents($startedPath)))->toBe('started');

        (new RemoveProjectMember)->handle($owner, $project, $member);
        DB::commit();

        $deadline = microtime(true) + 5;
        while (! file_get_contents($resultPath) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        expect(file_get_contents($resultPath))->not->toBeFalse();
        $result = json_decode((string) file_get_contents($resultPath), true, flags: JSON_THROW_ON_ERROR);
        $exitCode = proc_close($process);
        $process = null;

        expect($exitCode)->toBe(1)
            ->and($result['success'])->toBeFalse()
            ->and($task->fresh()->assignee_id)->toBeNull();
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        if (DB::transactionLevel() > 0) DB::rollBack();
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        @unlink($startedPath);
        @unlink($resultPath);
        @unlink($stdoutPath);
        @unlink($stderrPath);
    }
});
