<?php

namespace App\Policies;

use App\Domain\Collaboration\Enums\ProjectRole;
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
        return $this->role($user, $project) !== null;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canManageContent($user, $project);
    }

    public function changeStatus(User $user, Project $project): bool
    {
        return $this->canManageContent($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->canManageContent($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return in_array($this->role($user, $project), [ProjectRole::OWNER, ProjectRole::ADMIN], true);
    }

    public function export(User $user, Project $project): bool
    {
        return $this->canManageContent($user, $project);
    }

    public function viewAnalytics(User $user, Project $project): bool
    {
        return in_array($this->role($user, $project), [ProjectRole::OWNER, ProjectRole::ADMIN], true);
    }

    public function exportAny(User $user): bool
    {
        return Project::query()->accessibleBy($user)->where(function ($projects): void {
            $projects->whereHas('memberships', fn ($memberships) => $memberships->whereIn('role', [ProjectRole::OWNER->value, ProjectRole::ADMIN->value])->whereNull('removed_at'))
                ->orWhere(fn ($legacy) => $legacy->whereDoesntHave('memberships'));
        })->exists();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return in_array($this->role($user, $project), [ProjectRole::OWNER, ProjectRole::ADMIN], true);
    }

    public function manageRoles(User $user, Project $project): bool
    {
        return $this->role($user, $project) === ProjectRole::OWNER;
    }

    public function reorder(User $user, Project $project): bool
    {
        return $this->canManageContent($user, $project);
    }

    private function canManageContent(User $user, Project $project): bool
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
