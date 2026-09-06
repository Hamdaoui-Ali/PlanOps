<?php

namespace App\Domain\Collaboration\Actions;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Jobs\DeliverNotificationOutcome;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use App\Models\User as UserModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class InviteProjectMember
{
    public function handle(User $actor, Project $project, string $email, ProjectRole $role): ProjectInvitation
    {
        Gate::forUser($actor)->authorize('manageMembers', $project);
        $normalized = strtolower(trim($email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }
        if ($role === ProjectRole::OWNER || ($this->roleFor($actor, $project) === ProjectRole::ADMIN && $role !== ProjectRole::MEMBER)) {
            throw new AuthorizationException('This role cannot be invited by the current project member.');
        }

        $invitation = DB::transaction(function () use ($actor, $project, $email, $normalized, $role): ProjectInvitation {
            $project->newQuery()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();
            if (ProjectMembership::query()->where('project_id', $project->getKey())->whereHas('user', fn ($users) => $users->whereRaw('LOWER(email) = ?', [$normalized]))->whereNull('removed_at')->exists()) {
                throw ValidationException::withMessages(['email' => 'That person is already a project member.']);
            }
            if (ProjectInvitation::query()->where('project_id', $project->getKey())->where('normalized_email', $normalized)->whereNull('accepted_at')->whereNull('revoked_at')->exists()) {
                throw ValidationException::withMessages(['email' => 'A pending invitation already exists for that email.']);
            }

            $plain = bin2hex(random_bytes(32));
            $invitation = ProjectInvitation::create([
                'project_id' => $project->getKey(), 'email' => trim($email), 'normalized_email' => $normalized,
                'role' => $role, 'invited_by_user_id' => $actor->getKey(), 'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addDays(7), 'last_sent_at' => now(),
            ]);
            $invitation->setAttribute('plain_token', $plain);
            ProjectEvent::create(['project_id' => $project->getKey(), 'actor_user_id' => $actor->getKey(), 'event_type' => ProjectEventType::INVITATION_CREATED, 'metadata' => ['email' => $normalized, 'role' => $role->value]]);

            return $invitation;
        });

        $recipient = UserModel::query()->whereRaw('LOWER(email) = ?', [$normalized])->first();
        if ($recipient !== null) {
            $outcome = NotificationOutcome::invitationCreated(
                $invitation->getKey(),
                $project->getKey(),
                $recipient->getKey(),
                $project->name,
            );
            DB::afterCommit(fn (): mixed => DeliverNotificationOutcome::dispatch($outcome));
        }

        return $invitation;
    }

    private function roleFor(User $user, Project $project): ?ProjectRole
    {
        return ProjectMembership::query()
            ->where('project_id', $project->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('removed_at')
            ->first()?->role;
    }
}
