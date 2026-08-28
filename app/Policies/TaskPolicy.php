<?php

namespace App\Policies;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function create(User $user, Project $project): bool
    {
        return (string) $user->getKey() === (string) $project->user_id;
    }

    public function update(User $user, Task $task): bool
    {
        return (string) $user->getKey() === (string) $task->user_id;
    }

    public function changePriority(User $user, Task $task): bool
    {
        return (string) $user->getKey() === (string) $task->user_id;
    }

    public function changeDueDate(User $user, Task $task): bool
    {
        return (string) $user->getKey() === (string) $task->user_id;
    }

    public function delete(User $user, Task $task): bool
    {
        return (string) $user->getKey() === (string) $task->user_id;
    }

    public function restore(User $user, Task $task): bool
    {
        return (string) $user->getKey() === (string) $task->user_id;
    }
}
