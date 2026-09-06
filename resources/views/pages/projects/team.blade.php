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
                <section aria-labelledby="pending-invitations-heading" class="project-overview-summary">
                    <h2 id="pending-invitations-heading">Pending invitations</h2>
                    <ul>
                        @foreach ($project->invitations as $invitation)
                            <li><span>{{ $invitation->email }}</span> <span>Pending</span> <span>{{ $invitation->role->value }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </section>
    </div>
</x-app-layout>
