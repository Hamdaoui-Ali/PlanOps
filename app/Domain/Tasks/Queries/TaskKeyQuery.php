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

        $project = $task->relationLoaded('project')
            ? $task->project
            : $task->load('project')->project;

        if ($project === null || blank($project->key) || (int) $task->number < 1) {
            throw new LogicException('Cannot derive a display key without a valid task identity.');
        }

        return strtoupper($project->key).'-'.(int) $task->number;
    }
}
