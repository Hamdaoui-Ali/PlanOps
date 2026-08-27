<?php

namespace App\Domain\Activity\Queries;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class TaskActivityFeedQuery
{
    public function paginate(
        User|int $owner,
        array $filters = [],
        int $perPage = 50,
    ): LengthAwarePaginator {
        $perPage = min(50, max(1, $perPage));
        $eventType = $filters['event_type'] ?? null;
        $eventType = $eventType instanceof TaskActivityType ? $eventType->value : $eventType;

        return TaskActivity::query()
            ->ownedBy($owner)
            ->with([
                'project:id,user_id,name,key',
                'task:id,user_id,project_id,number,title,deleted_at',
            ])
            ->when(array_key_exists('project_id', $filters), fn ($query) => $query->where('project_id', $filters['project_id']))
            ->when(array_key_exists('task_id', $filters), fn ($query) => $query->where('task_id', $filters['task_id']))
            ->when($eventType !== null, fn ($query) => $query->where('event_type', $eventType))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['until'] ?? null, fn ($query, $until) => $query->where('created_at', '<', $until))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function forTask(User|int $owner, Task|int $task): Collection
    {
        $taskId = $task instanceof Task ? $task->getKey() : $task;

        return TaskActivity::query()
            ->ownedBy($owner)
            ->where('task_id', $taskId)
            ->with([
                'project:id,user_id,name,key',
                'task:id,user_id,project_id,number,title,deleted_at',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
