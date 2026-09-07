<x-app-layout>
    <div class="planops-console">
        <section class="project-overview-page" aria-labelledby="team-heading">
            @if (session('status')) <div class="planops-flash" role="status">{{ session('status') }}</div> @endif
            <header class="project-overview-header">
                <div><p class="planops-eyebrow">Project / team</p><h1 id="team-heading">{{ $project->name }} team</h1><p>Manage who can access this project.</p></div>
                <a href="{{ route('projects.show', $project) }}" class="planops-button planops-button-secondary">Back to project</a>
            </header>
            @can('manageMembers', $project)
                <form method="POST" action="{{ route('projects.team.invitations.store', $project) }}" class="project-overview-summary">
                    @csrf
                    <label>Email <input type="email" name="email" required value="{{ old('email') }}"></label>
                    <label>Role <select name="role"><option value="MEMBER">Member</option><option value="ADMIN">Admin</option></select></label>
                    <button class="planops-button planops-button-primary" type="submit">Invite member</button>
                </form>
            @endcan
            <div class="projects-ledger-wrap"><table class="projects-ledger"><caption class="sr-only">Members of {{ $project->name }}</caption><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr></thead><tbody>
            @foreach ($project->activeMemberships as $membership)<tr><td>{{ $membership->user->name }}</td><td>{{ $membership->user->email }}</td><td>{{ $membership->role->value }}</td><td>
                @can('manageRoles', $project) @if ($membership->role !== \App\Domain\Collaboration\Enums\ProjectRole::OWNER)<form method="POST" action="{{ route('projects.team.members.update', [$project, $membership]) }}" style="display:inline">@csrf @method('PATCH')<select name="role"><option value="MEMBER" @selected($membership->role->value === 'MEMBER')>Member</option><option value="ADMIN" @selected($membership->role->value === 'ADMIN')>Admin</option></select><button type="submit">Save</button></form>@endif @endcan
                @can('manageMembers', $project) @if ($membership->role !== \App\Domain\Collaboration\Enums\ProjectRole::OWNER)<form method="POST" action="{{ route('projects.team.members.destroy', [$project, $membership]) }}" style="display:inline">@csrf @method('DELETE')<button type="submit">Remove</button></form>@endif @endcan
            </td></tr>@endforeach
            </tbody></table></div>
            @if ($project->invitations->isNotEmpty())
                <section aria-labelledby="pending-invitations-heading" class="pending-invitations-panel">
                    <header class="pending-invitations-header">
                        <div>
                            <p class="planops-eyebrow">Awaiting response</p>
                            <h2 id="pending-invitations-heading">Pending invitations</h2>
                            <p>Keep track of collaborators who have not joined yet.</p>
                        </div>
                        <span class="pending-invitations-count">{{ $project->invitations->count() }} {{ $project->invitations->count() === 1 ? 'invite' : 'invites' }}</span>
                    </header>
                    <ul class="pending-invitations-list">
                        @foreach ($project->invitations as $invitation)
                            <li class="pending-invitation-row">
                                <span class="pending-invitation-icon" aria-hidden="true"><i class="ph ph-envelope-simple"></i></span>
                                <div class="pending-invitation-details">
                                    <strong>{{ $invitation->email }}</strong>
                                    <span>Awaiting acceptance</span>
                                </div>
                                <div class="pending-invitation-meta">
                                    <span class="pending-invitation-status"><i class="ph ph-clock" aria-hidden="true"></i>Pending</span>
                                    <span class="pending-invitation-role">{{ $invitation->role->value }}</span>
                                </div>
                                @can('manageMembers', $project)
                                    @if ($invitation->isPending())
                                        <form method="POST" action="{{ route('invitations.revoke', $invitation, absolute: false) }}" class="pending-invitation-action">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="planops-button planops-button-secondary"><i class="ph ph-x" aria-hidden="true"></i>Cancel</button>
                                        </form>
                                    @endif
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </section>
    </div>
</x-app-layout>
