<?php

namespace App\Domain\Labels\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Labels\Models\Label;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AttachLabelToTask
{
    public function handle(User $user, Task $task, Label $label): Task
    {
        Gate::forUser($user)->authorize('attach', [$label, $task]);

        $updatedTask = DB::transaction(function () use ($user, $task, $label): Task {
            $ownedLabel = Label::query()->ownedBy($user)->whereKey($label->getKey())->lockForUpdate()->firstOrFail();
            $ownedTask = Task::query()->ownedBy($user)->whereKey($task->getKey())->lockForUpdate()->firstOrFail();

            if (! $ownedTask->labels()->whereKey($ownedLabel->getKey())->exists()) {
                $ownedTask->labels()->attach($ownedLabel->getKey());

                app(TaskActivityRecorder::class)->record(
                    $ownedTask,
                    TaskActivityType::LABEL_ADDED,
                    'label_id',
                    null,
                    $ownedLabel->getKey(),
                    ['label' => ['id' => $ownedLabel->getKey(), 'name' => $ownedLabel->name]],
                );
            }

            return $ownedTask;
        });

        return Task::query()->ownedBy($user)->whereKey($updatedTask->getKey())->firstOrFail();
    }
}
