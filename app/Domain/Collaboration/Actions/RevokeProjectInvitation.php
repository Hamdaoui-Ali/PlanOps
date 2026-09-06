<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RevokeProjectInvitation
{
    public function handle(User $actor, ProjectInvitation $invitation): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $invitation->project);
        DB::transaction(function () use ($actor, $invitation): void {
            $locked = ProjectInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be revoked.']);
            }
            $locked->forceFill(['revoked_at' => now()])->save();
            ProjectEvent::create(['project_id' => $locked->project_id, 'actor_user_id' => $actor->getKey(), 'event_type' => ProjectEventType::INVITATION_REVOKED, 'metadata' => ['email' => $locked->normalized_email]]);
        });
    }
}
