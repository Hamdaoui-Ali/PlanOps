<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderTasks
{
    /**
     * @param array<int, int|string> $orderedTaskIds
     */
    public function handle(User $owner, Project $project, TaskStatus $status, array $orderedTaskIds): void
    {
        DB::transaction(function () use ($owner, $project, $status, $orderedTaskIds): void {
            $ids = array_map('intval', $orderedTaskIds);

            if (
                count($ids) !== count($orderedTaskIds)
                || collect($orderedTaskIds)->contains(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) === false)
                || count($ids) !== count(array_unique($ids))
            ) {
                $this->invalidOrder('The task order must contain unique task ids.');
            }

            if (! Project::query()->ownedBy($owner)->whereKey($project->getKey())->exists()) {
                $this->invalidOrder('The project is not available.');
            }

            $tasks = Task::query()
                ->ownedBy($owner)
                ->where('project_id', $project->getKey())
                ->whereNull('parent_task_id')
                ->where('status', $status->value)
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($tasks->count() !== count($ids)) {
                $this->invalidOrder('Every task must belong to this project and status column.');
            }

            foreach ($ids as $position => $id) {
                $tasks->get($id)->forceFill(['position' => $position])->save();
            }
        });
    }

    private function invalidOrder(string $message): never
    {
        throw ValidationException::withMessages(['ordered_task_ids' => $message]);
    }
}
