<?php

namespace App\Domain\Tasks\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProjectBoardQuery
{
    /**
     * @return array<string, Collection<int, Task>>
     */
    public function for(User|int $owner, Project $project, bool $includeCancelled = false): array
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        $ownedProject = Project::query()
            ->ownedBy($ownerId)
            ->whereKey($project->getKey())
            ->firstOrFail();

        $statuses = collect(TaskStatus::cases())
            ->when(! $includeCancelled, fn ($statuses) => $statuses->reject(
                fn (TaskStatus $status): bool => $status === TaskStatus::CANCELLED,
            ));

        $tasks = Task::query()
            ->ownedBy($ownerId)
            ->where('project_id', $ownedProject->getKey())
            ->whereNull('parent_task_id')
            ->when(! $includeCancelled, fn (Builder $query): Builder => $query->where('status', '!=', TaskStatus::CANCELLED->value))
            ->with([
                'project',
                'labels',
                'children' => fn (HasMany $children): HasMany => $children
                    ->withCount([
                        'children',
                        'children as eligible_children_count' => fn (Builder $grandchildren): Builder => $grandchildren
                            ->where('status', '!=', TaskStatus::CANCELLED->value),
                        'children as completed_children_count' => fn (Builder $grandchildren): Builder => $grandchildren
                            ->where('status', TaskStatus::DONE->value),
                    ])
                    ->orderBy('position')
                    ->orderBy('number'),
            ])
            ->withCount([
                'children',
                'children as eligible_children_count' => fn (Builder $children): Builder => $children
                    ->where('status', '!=', TaskStatus::CANCELLED->value),
                'children as completed_children_count' => fn (Builder $children): Builder => $children
                    ->where('status', TaskStatus::DONE->value),
            ])
            ->orderBy('position')
            ->orderBy('number')
            ->get();

        return $statuses
            ->mapWithKeys(fn (TaskStatus $status): array => [
                $status->value => $tasks->filter(fn (Task $task): bool => $task->status === $status)->values(),
            ])
            ->all();
    }
}
