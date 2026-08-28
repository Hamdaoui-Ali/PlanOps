<?php

namespace App\Policies;

use App\Domain\Labels\Models\Label;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

class LabelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Label $label): bool
    {
        return (string) $user->getKey() === (string) $label->user_id;
    }

    public function attach(User $user, Label $label, Task $task): bool
    {
        return (string) $user->getKey() === (string) $label->user_id
            && (string) $user->getKey() === (string) $task->user_id;
    }

    public function detach(User $user, Label $label, Task $task): bool
    {
        return $this->attach($user, $label, $task);
    }
}
