<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectOverviewQuery
{
    public function for(User|int $owner, Project $project): Project
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return Project::query()
            ->ownedBy($ownerId)
            ->whereKey($project->getKey())
            ->with([
                'tasks' => fn (HasMany $tasks): HasMany => $tasks
                    ->whereNull('parent_task_id')
                    ->with('children')
                    ->withCount('children')
                    ->orderBy('position')
                    ->orderBy('number'),
            ])
            ->withCount([
                'tasks as eligible_task_count' => fn (Builder $tasks): Builder => $tasks
                    ->whereNull('parent_task_id')
                    ->where('status', '!=', TaskStatus::CANCELLED->value),
                'tasks as completed_task_count' => fn (Builder $tasks): Builder => $tasks
                    ->whereNull('parent_task_id')
                    ->where('status', TaskStatus::DONE->value),
            ])
            ->firstOrFail();
    }
}
