<?php

namespace App\Policies;

use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    public function changePriority(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    public function changeDueDate(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    public function view(User $user, Task $task): bool
    {
        return $task->project !== null && $this->role($user, $task->project) !== null;
    }

    public function changeStatus(User $user, Task $task): bool
    {
        if (! $task->project || $task->project->archived_at !== null) {
            return false;
        }
        if ($this->legacyTaskOwner($user, $task)) {
            return true;
        }
        $role = $this->role($user, $task->project);

        return in_array($role, [ProjectRole::OWNER, ProjectRole::ADMIN], true)
            || ($role === ProjectRole::MEMBER && (int) $task->assignee_id === (int) $user->getKey());
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->canManageTask($user, $task);
    }

    public function reorder(User $user, Project $project): bool
    {
        return $this->canManage($user, $project);
    }

    private function canManageTask(User $user, Task $task): bool
    {
        return $task->project !== null && ($this->canManage($user, $task->project) || $this->legacyTaskOwner($user, $task));
    }

    private function legacyTaskOwner(User $user, Task $task): bool
    {
        return (string) $task->user_id === (string) $user->getKey()
            && $task->project?->memberships()->doesntExist();
    }

    private function canManage(User $user, Project $project): bool
    {
        return in_array($this->role($user, $project), [ProjectRole::OWNER, ProjectRole::ADMIN], true)
            && $project->archived_at === null;
    }

    private function role(User $user, Project $project): ?ProjectRole
    {
        $membership = $project->memberships()->where('user_id', $user->getKey())->whereNull('removed_at')->first();
        if ($membership) {
            return $membership->role;
        }

        return ! $project->memberships()->exists() && (string) $user->getKey() === (string) $project->user_id
            ? ProjectRole::OWNER : null;
    }
}
