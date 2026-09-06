<?php

namespace App\Policies;

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

class LabelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user, ?Project $project = null): bool
    {
        return $project === null || $user->can('update', $project);
    }

    public function view(User $user, Label $label): bool
    {
        return $label->project !== null
            ? $user->can('view', $label->project)
            : (string) $user->getKey() === (string) $label->user_id;
    }

    public function delete(User $user, Label $label): bool
    {
        return $label->project !== null ? $user->can('update', $label->project) : (string) $user->getKey() === (string) $label->user_id;
    }

    public function attach(User $user, Label $label, Task $task): bool
    {
        if ($label->project !== null) {
            return $task->project_id === $label->project_id && $user->can('update', $label->project);
        }

        return (string) $user->getKey() === (string) $label->user_id
            && (string) $user->getKey() === (string) $task->user_id;
    }

    public function detach(User $user, Label $label, Task $task): bool
    {
        return $this->attach($user, $label, $task);
    }
}
