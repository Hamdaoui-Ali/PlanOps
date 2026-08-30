<?php

namespace App\Domain\Export\Queries;

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\LazyCollection;

final class ExportQueryService
{
    public function projects(User $owner): LazyCollection
    {
        return Project::query()->ownedBy($owner)->withCount([
            'tasks as eligible_task_count' => fn ($tasks) => $tasks->whereNull('parent_task_id')->where('status', '!=', 'CANCELLED'),
            'tasks as completed_task_count' => fn ($tasks) => $tasks->whereNull('parent_task_id')->where('status', 'DONE'),
        ])->orderBy('id')->lazyById(100);
    }

    public function tasks(User $owner): LazyCollection
    {
        return Task::query()->ownedBy($owner)->with(['project:id,name,key', 'parent:id,project_id,number', 'labels:id,name'])->orderBy('id')->lazyById(100);
    }

    public function activity(User $owner): LazyCollection
    {
        return TaskActivity::query()->ownedBy($owner)->with(['project:id,name,key', 'task:id,project_id,number,title,deleted_at'])->orderBy('id')->lazyById(100);
    }
}
