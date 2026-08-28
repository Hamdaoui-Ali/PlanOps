<?php

namespace App\Domain\Labels\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Labels\Models\Label;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DetachLabelFromTask
{
    public function handle(User $user, Task $task, Label $label): Task
    {
        Gate::forUser($user)->authorize('detach', [$label, $task]);

        $updatedTask = DB::transaction(function () use ($user, $task, $label): Task {
            $ownedTask = Task::query()->ownedBy($user)->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
            $ownedLabel = Label::query()->ownedBy($user)->whereKey($label->getKey())->lockForUpdate()->firstOrFail();

            if ($ownedTask->labels()->whereKey($ownedLabel->getKey())->exists()) {
                $ownedTask->labels()->detach($ownedLabel->getKey());

                app(TaskActivityRecorder::class)->record(
                    $ownedTask,
                    TaskActivityType::LABEL_REMOVED,
                    'label_id',
                    $ownedLabel->getKey(),
                    null,
                    ['label' => ['id' => $ownedLabel->getKey(), 'name' => $ownedLabel->name]],
                );
            }

            return $ownedTask;
        });

        return Task::query()->ownedBy($user)->whereKey($updatedTask->getKey())->firstOrFail();
    }
}
