<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    DB::statement("SET lock_timeout = '15s'");
    $backend = DB::selectOne('select pg_backend_pid() as pid');
    file_put_contents($argv[3], (string) $backend->pid, LOCK_EX);
    $project = Project::query()->findOrFail((int) $argv[1]);
    $admin = User::query()->findOrFail((int) $argv[2]);
    (new CreateTask)->handle($admin, $project, ['title' => 'Stale admin task']);
    file_put_contents($argv[4], json_encode(['success' => true], JSON_THROW_ON_ERROR), LOCK_EX);
    exit(0);
} catch (Throwable $exception) {
    file_put_contents($argv[4], json_encode(['success' => false, 'exception' => $exception::class], JSON_THROW_ON_ERROR), LOCK_EX);
    exit(1);
}
