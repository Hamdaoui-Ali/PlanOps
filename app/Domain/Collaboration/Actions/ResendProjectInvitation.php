<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ResendProjectInvitation
{
    public function handle(User $actor, ProjectInvitation $invitation): ProjectInvitation
    {
        Gate::forUser($actor)->authorize('manageMembers', $invitation->project);
        return DB::transaction(function () use ($actor, $invitation): ProjectInvitation {
            $locked = ProjectInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isPending()) throw ValidationException::withMessages(['invitation' => 'Only pending invitations can be resent.']);
            $plain = bin2hex(random_bytes(32));
            $locked->forceFill(['token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDays(7), 'last_sent_at' => now()])->save();
            $locked->setAttribute('plain_token', $plain);
            ProjectEvent::create(['project_id' => $locked->project_id, 'actor_user_id' => $actor->getKey(), 'event_type' => ProjectEventType::INVITATION_RESENT, 'metadata' => ['email' => $locked->normalized_email]]);
            return $locked;
        });
    }
}
