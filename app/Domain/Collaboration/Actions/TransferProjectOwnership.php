<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class TransferProjectOwnership
{
    public function handle(User $actor, Project $project, User $newOwner): Project
    {
        Gate::forUser($actor)->authorize('transferOwnership', $project);
        return DB::transaction(function () use ($actor, $project, $newOwner): Project {
            $lockedProject = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            $current = ProjectMembership::query()->where('project_id', $lockedProject->getKey())->where('user_id', $actor->getKey())->whereNull('removed_at')->lockForUpdate()->firstOrFail();
            $target = ProjectMembership::query()->where('project_id', $lockedProject->getKey())->where('user_id', $newOwner->getKey())->whereNull('removed_at')->lockForUpdate()->first();
            if (! $target) throw ValidationException::withMessages(['owner' => 'The new owner must be an active project member.']);
            $current->forceFill(['role' => ProjectRole::ADMIN])->save();
            $target->forceFill(['role' => ProjectRole::OWNER])->save();
            $lockedProject->forceFill(['owner_id' => $newOwner->getKey()])->save();
            ProjectEvent::create(['project_id' => $lockedProject->getKey(), 'actor_user_id' => $actor->getKey(), 'subject_user_id' => $newOwner->getKey(), 'event_type' => ProjectEventType::OWNERSHIP_TRANSFERRED, 'metadata' => ['previous_owner_id' => $actor->getKey()]]);
            return $lockedProject->fresh();
        });
    }
}
