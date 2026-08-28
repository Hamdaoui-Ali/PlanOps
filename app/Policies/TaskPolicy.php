<?php

namespace App\Policies;

use App\Domain\Projects\Models\Project;
use App\Models\User;

class TaskPolicy
{
    public function create(User $user, Project $project): bool
    {
        return (string) $user->getKey() === (string) $project->user_id;
    }
}
