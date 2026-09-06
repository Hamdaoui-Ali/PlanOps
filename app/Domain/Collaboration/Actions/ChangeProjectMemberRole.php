<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeProjectMemberRole
{
    public function handle(User $actor, ProjectMembership $membership, ProjectRole $role): ProjectMembership
    {
        Gate::forUser($actor)->authorize('manageRoles', $membership->project);
        if ($role === ProjectRole::OWNER) throw ValidationException::withMessages(['role' => 'Use ownership transfer to change the owner.']);
        return DB::transaction(function () use ($actor, $membership, $role): ProjectMembership {
            $locked = ProjectMembership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->removed_at !== null || $locked->role === ProjectRole::OWNER) throw ValidationException::withMessages(['role' => 'The owner role cannot be changed here.']);
            $old = $locked->role;
            $locked->forceFill(['role' => $role])->save();
            ProjectEvent::create(['project_id' => $locked->project_id, 'actor_user_id' => $actor->getKey(), 'subject_user_id' => $locked->user_id, 'event_type' => ProjectEventType::MEMBER_ROLE_CHANGED, 'metadata' => ['old_role' => $old->value, 'new_role' => $role->value]]);
            return $locked;
        });
    }
}
