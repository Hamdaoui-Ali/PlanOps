<?php

namespace App\Policies;

use App\Domain\Projects\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return (int) $user->getKey() === (int) $project->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }

    public function changeStatus(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }
}
