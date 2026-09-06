<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$taskId = (int) ($argv[1] ?? 0);
$actorId = (int) ($argv[2] ?? 0);
$assigneeId = (int) ($argv[3] ?? 0);
$startedPath = $argv[4] ?? '';
$resultPath = $argv[5] ?? '';

file_put_contents($startedPath, 'started', LOCK_EX);

try {
    $task = App\Domain\Tasks\Models\Task::query()->findOrFail($taskId);
    $actor = App\Models\User::query()->findOrFail($actorId);
    $assignee = App\Models\User::query()->findOrFail($assigneeId);
    (new App\Domain\Tasks\Actions\AssignTask)->handle($actor, $task, $assignee);
    file_put_contents($resultPath, json_encode(['success' => true], JSON_THROW_ON_ERROR), LOCK_EX);
    exit(0);
} catch (Throwable $exception) {
    file_put_contents($resultPath, json_encode(['success' => false, 'class' => $exception::class], JSON_THROW_ON_ERROR), LOCK_EX);
    exit(1);
}
