<?php

namespace App\Domain\Tasks\Queries;

use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskDetailQuery
{
    public function for(User|int $owner, Task $task): Task
    {
        $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

        return Task::query()
            ->ownedBy($ownerId)
            ->whereKey($task->getKey())
            ->with([
                'project',
                'parent',
                'children' => fn (HasMany $children): HasMany => $children
                    ->withCount('children')
                    ->orderBy('position')
                    ->orderBy('number'),
            ])
            ->firstOrFail();
    }
}
