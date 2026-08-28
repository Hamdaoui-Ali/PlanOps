<?php

namespace App\Domain\Labels\Actions;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Labels\Models\Label;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteLabel
{
    public function handle(User $user, Label $label): void
    {
        Gate::forUser($user)->authorize('delete', $label);

        DB::transaction(function () use ($user, $label): void {
            $ownedLabel = Label::query()->ownedBy($user)->whereKey($label->getKey())->lockForUpdate()->first();

            if ($ownedLabel === null) {
                return;
            }

            $tasks = $ownedLabel->tasks()->ownedBy($user)->lockForUpdate()->get();

            foreach ($tasks as $task) {
                $task->labels()->detach($ownedLabel->getKey());

                app(TaskActivityRecorder::class)->record(
                    $task,
                    TaskActivityType::LABEL_REMOVED,
                    'label_id',
                    $ownedLabel->getKey(),
                    null,
                    ['label' => ['id' => $ownedLabel->getKey(), 'name' => $ownedLabel->name]],
                );
            }

            $ownedLabel->delete();
        });
    }
}
