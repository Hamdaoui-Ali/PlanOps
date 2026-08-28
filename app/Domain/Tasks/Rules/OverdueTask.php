<?php

namespace App\Domain\Tasks\Rules;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use Carbon\CarbonImmutable;

final class OverdueTask
{
    public function passes(Task $task, CarbonImmutable $userLocalDate): bool
    {
        if ($task->due_on === null) {
            return false;
        }

        if (in_array($task->status, [TaskStatus::DONE, TaskStatus::CANCELLED], true)) {
            return false;
        }

        return $userLocalDate->toDateString() > $task->due_on->toDateString();
    }
}
