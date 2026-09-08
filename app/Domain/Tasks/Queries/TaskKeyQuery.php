<?php

namespace App\Domain\Tasks\Queries;

use App\Domain\Tasks\Models\Task;
use LogicException;

class TaskKeyQuery
{
    public function displayKey(Task $task): string
    {
        if (! $task->exists) {
            throw new LogicException('Cannot derive a display key for an unsaved task.');
        }

        $project = $task->project;

        if (
            $task->user_id === null
            || $task->project_id === null
            || $project === null
            || (string) $project->getKey() !== (string) $task->project_id
            || blank($project->key)
            || (int) $task->number < 1
        ) {
            throw new LogicException('Cannot derive a display key without a valid task identity.');
        }

        return strtoupper($project->key).'-'.(int) $task->number;
    }
}
