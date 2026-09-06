<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptProjectInvitation
{
    public function handle(User $user, string $rawToken): ProjectMembership
    {
        if ($rawToken === '') {
            throw ValidationException::withMessages(['token' => 'This invitation is invalid.']);
        }

        return DB::transaction(function () use ($user, $rawToken): ProjectMembership {
            $invitation = ProjectInvitation::query()->where('token_hash', hash('sha256', $rawToken))->lockForUpdate()->first();
            if (! $invitation || ! $invitation->isPending()) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or expired.']);
            }

            return $this->acceptLockedInvitation($user, $invitation);
        });
    }

    public function handleInvitation(User $user, ProjectInvitation $invitation): ProjectMembership
    {
        return DB::transaction(function () use ($user, $invitation): ProjectMembership {
            $locked = ProjectInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->isPending()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or expired.']);
            }

            return $this->acceptLockedInvitation($user, $locked);
        });
    }

    private function acceptLockedInvitation(User $user, ProjectInvitation $invitation): ProjectMembership
    {
        if (strtolower(trim($user->email)) !== $invitation->normalized_email) {
            throw ValidationException::withMessages(['email' => 'This invitation belongs to a different email address.']);
        }

        $membership = ProjectMembership::query()->where('project_id', $invitation->project_id)->where('user_id', $user->getKey())->lockForUpdate()->first();
        if ($membership) {
            $membership->forceFill(['role' => $invitation->role, 'joined_at' => now(), 'removed_at' => null, 'removed_by_user_id' => null])->save();
        } else {
            $membership = ProjectMembership::create(['project_id' => $invitation->project_id, 'user_id' => $user->getKey(), 'role' => $invitation->role, 'joined_at' => now()]);
        }
        $invitation->forceFill(['accepted_at' => now()])->save();
        ProjectEvent::create(['project_id' => $invitation->project_id, 'actor_user_id' => $user->getKey(), 'subject_user_id' => $user->getKey(), 'event_type' => ProjectEventType::INVITATION_ACCEPTED, 'metadata' => ['role' => $membership->role->value]]);

        return $membership;
    }
}
