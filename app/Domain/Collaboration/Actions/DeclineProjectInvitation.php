<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeclineProjectInvitation
{
    public function handle(User $user, ProjectInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation): void {
            $locked = ProjectInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();
            if (! $locked || ! $locked->isPending()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is invalid or expired.']);
            }
            if (strtolower(trim($user->email)) !== $locked->normalized_email) {
                throw ValidationException::withMessages(['email' => 'This invitation belongs to a different email address.']);
            }

            $locked->forceFill(['revoked_at' => now()])->save();
            ProjectEvent::create([
                'project_id' => $locked->project_id,
                'actor_user_id' => $user->getKey(),
                'subject_user_id' => $user->getKey(),
                'event_type' => ProjectEventType::INVITATION_REVOKED,
                'metadata' => ['reason' => 'recipient_declined'],
            ]);
        });
    }
}
