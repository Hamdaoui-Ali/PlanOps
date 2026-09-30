<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Collaboration\Actions\ChangeProjectMemberRole;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

test('PostgreSQL denies task creation after an admin is demoted while waiting for the project lock', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql' || ! function_exists('proc_open')) {
        $this->markTestSkipped('PostgreSQL and proc_open are required for the independent-process demotion race.');
    }

    $defaultConnection = DB::getDefaultConnection();
    $connectionConfig = DB::connection()->getConfig();
    $raceConnection = 'task_creation_demotion_race';
    $connectionConfig['name'] = $raceConnection;
    config([
        "database.connections.$raceConnection" => $connectionConfig,
        'database.default' => $raceConnection,
    ]);
    DB::setDefaultConnection($raceConnection);
    $process = null;
    $paths = [];
    $owner = $admin = $project = null;

    try {
        // Commit fixtures on an independent connection, outside RefreshDatabase's transaction.
        $owner = User::factory()->connection($raceConnection)->create();
        $admin = User::factory()->connection($raceConnection)->create();
        $project = Project::factory()->connection($raceConnection)->for($owner)->create(['owner_id' => $owner->id, 'next_task_number' => 7]);
        $membership = ProjectMembership::factory()->connection($raceConnection)->admin()->create(['project_id' => $project->id, 'user_id' => $admin->id]);
        foreach (['pid', 'result', 'stdout', 'stderr'] as $name) {
            $paths[$name] = tempnam(sys_get_temp_dir(), 'planops-demotion-'.$name.'-');
        }

        DB::beginTransaction();
        Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
        $childEnvironment = getenv();
        if (! is_array($childEnvironment)) {
            $childEnvironment = [];
        }
        $childEnvironment['APP_ENV'] = 'testing';
        $childEnvironment['APP_KEY'] = (string) config('app.key');
        $childEnvironment['DB_CONNECTION'] = 'pgsql';
        $childEnvironment['DB_URL'] = '';
        $childEnvironment['DB_HOST'] = (string) $connectionConfig['host'];
        $childEnvironment['DB_PORT'] = (string) $connectionConfig['port'];
        $childEnvironment['DB_DATABASE'] = (string) $connectionConfig['database'];
        $childEnvironment['DB_USERNAME'] = (string) $connectionConfig['username'];
        $childEnvironment['DB_PASSWORD'] = (string) $connectionConfig['password'];
        $childEnvironment['DB_SSLMODE'] = (string) ($connectionConfig['sslmode'] ?? 'prefer');

        $process = proc_open([
            PHP_BINARY, base_path('tests/Support/task_creation_race_worker.php'),
            $project->id, $admin->id, $paths['pid'], $paths['result'],
        ], [0 => ['pipe', 'r'], 1 => ['file', $paths['stdout'], 'a'], 2 => ['file', $paths['stderr'], 'a']], $pipes, base_path(), $childEnvironment);
        expect(is_resource($process))->toBeTrue();
        fclose($pipes[0]);

        $waiting = false;
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $backendId = (int) file_get_contents($paths['pid']);
            $waiting = $backendId > 0 && DB::table('pg_locks')->where('pid', $backendId)->where('granted', false)->exists();
            if ($waiting) {
                break;
            }
            usleep(10_000);
        }
        expect($waiting)->toBeTrue('The creator must pass preauthorization and wait for the locked project.');

        (new ChangeProjectMemberRole)->handle($owner, $membership, ProjectRole::MEMBER);
        DB::commit();

        $deadline = microtime(true) + 10;
        while (file_get_contents($paths['result']) === '' && microtime(true) < $deadline) {
            usleep(10_000);
        }
        $result = json_decode((string) file_get_contents($paths['result']), true, flags: JSON_THROW_ON_ERROR);
        $exitCode = proc_close($process);
        $process = null;

        expect($exitCode)->toBe(1)
            ->and($result['exception'])->toBe(AuthorizationException::class)
            ->and($membership->fresh()->role)->toBe(ProjectRole::MEMBER)
            ->and(Task::query()->where('project_id', $project->id)->count())->toBe(0)
            ->and(TaskActivity::query()->where('project_id', $project->id)->count())->toBe(0)
            ->and($project->fresh()->next_task_number)->toBe(7);
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
        if ($project !== null) {
            DB::table('task_activities')->where('project_id', $project->id)->delete();
            DB::table('tasks')->where('project_id', $project->id)->delete();
            $project->delete();
        }
        $admin?->delete();
        $owner?->delete();
        foreach ($paths as $path) {
            @unlink($path);
        }
        config(['database.default' => $defaultConnection]);
        DB::setDefaultConnection($defaultConnection);
        DB::purge($raceConnection);
        config(["database.connections.$raceConnection" => null]);
    }
});
