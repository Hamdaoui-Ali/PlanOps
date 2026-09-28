<x-app-layout>
    @php
        $hasWork = $snapshot->unassignedCount > 0 || $snapshot->members->contains(fn ($member): bool => ($member->activeCount + $member->blockedCount + $member->overdueCount + $member->completedThisWeekCount) > 0);
    @endphp

    <div class="planops-console">
        <section class="project-overview-page team-work-page" aria-labelledby="team-work-heading">
            <header class="project-overview-header">
                <div>
                    <p class="planops-eyebrow">Project / workload</p>
                    <h1 id="team-work-heading">Team Work</h1>
                    <p>{{ $snapshot->project->name }} workload visibility for project managers.</p>
                </div>
                <div class="project-overview-actions">
                    <a href="{{ route('projects.show', $snapshot->project) }}" class="planops-button planops-button-secondary">Project overview</a>
                    <a href="{{ route('projects.team', $snapshot->project) }}" class="planops-button planops-button-secondary">Team</a>
                </div>
            </header>

            <section class="team-work-summary" aria-labelledby="workload-overview-heading">
                <div class="team-work-summary-heading">
                    <p class="planops-eyebrow">Manager view</p>
                    <h2 id="workload-overview-heading">Workload overview</h2>
                    <p>See where open work needs attention without turning the team into a leaderboard.</p>
                </div>
                <div class="team-work-metrics">
                    <div class="team-work-metric" data-metric="unassigned">
                        <span>Unassigned work</span>
                        <strong>{{ $snapshot->unassignedCount }}</strong>
                        <small>Open top-level tasks</small>
                    </div>
                    <div class="team-work-metric" data-metric="members">
                        <span>Team members</span>
                        <strong>{{ $snapshot->members->count() }}</strong>
                        <small>Active project members</small>
                    </div>
                </div>
            </section>

            <section class="team-work-table-panel" aria-labelledby="team-work-table-heading">
                <div class="team-work-section-heading">
                    <div>
                        <p class="planops-eyebrow">Workload detail</p>
                        <h2 id="team-work-table-heading">Workload by member</h2>
                    </div>
                    <span>Completed this week</span>
                </div>

                <div class="team-work-table-wrap" role="region" aria-label="Workload by member table, scroll horizontally to see all metrics" tabindex="0">
                    <table class="team-work-table">
                        <caption class="sr-only">Workload by active member for {{ $snapshot->project->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Member</th>
                                <th scope="col">Active work</th>
                                <th scope="col">Blocked</th>
                                <th scope="col">Overdue</th>
                                <th scope="col">Completed this week</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshot->members as $member)
                                <tr>
                                    <th scope="row">
                                        <span class="team-work-member-name">{{ $member->name }}</span>
                                        <span class="team-work-member-meta">{{ str($member->role->value)->title() }} · {{ $member->email }}</span>
                                    </th>
                                    <td data-label="Active work">{{ $member->activeCount }}</td>
                                    <td data-label="Blocked">{{ $member->blockedCount }}</td>
                                    <td data-label="Overdue">{{ $member->overdueCount }}</td>
                                    <td data-label="Completed this week">{{ $member->completedThisWeekCount }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (! $hasWork)
                    <div class="team-work-empty" role="status">
                        <i class="ph ph-users-three" aria-hidden="true"></i>
                        <h3>No active workload yet.</h3>
                        <p>Assign work to a project member to make the next step visible here.</p>
                    </div>
                @endif
            </section>
        </section>
    </div>
</x-app-layout>
