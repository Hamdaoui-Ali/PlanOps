<x-app-layout>
    @php
        $statusLabels = [
            'BACKLOG' => 'Backlog',
            'NOT_STARTED' => 'Not started',
            'IN_PROGRESS' => 'In progress',
            'IN_REVIEW' => 'In review',
            'BLOCKED' => 'Blocked',
            'DONE' => 'Done',
        ];
        $statusColors = [
            'BACKLOG' => 'is-muted',
            'NOT_STARTED' => 'is-neutral',
            'IN_PROGRESS' => 'is-accent',
            'IN_REVIEW' => 'is-focus',
            'BLOCKED' => 'is-warning',
            'DONE' => 'is-success',
        ];
        $statusTotal = array_sum($snapshot->statusDistribution);
    @endphp

    <div class="planops-console">
        <section class="analytics-page team-analytics-page" aria-labelledby="team-analytics-heading">
            <header class="dashboard-header team-analytics-header">
                <div>
                    <p class="planops-eyebrow">Project / team measurement</p>
                    <div class="team-analytics-title-row">
                        <h1 id="team-analytics-heading">Team Analytics</h1>
                        <span class="team-analytics-access">Owners &amp; Admins only</span>
                    </div>
                    <p>{{ $project->name }} ({{ $project->key }}) - Aggregate project flow and current workload attention.</p>
                </div>
                <div class="project-overview-actions">
                    <a href="{{ route('projects.show', $project) }}" class="planops-button planops-button-secondary">Project overview</a>
                    <a href="{{ route('projects.team.work', $project) }}" class="planops-button planops-button-secondary">Team Work</a>
                </div>
            </header>

            <x-dashboard.period-selector :selection="$selection" :action="route('projects.team.analytics', $project)" />
            <section class="dashboard-period-banner">
                <span class="planops-eyebrow">Selected period</span>
                <strong>{{ $snapshot->reportPeriod->label }}</strong>
            </section>

            <section class="dashboard-kpi-grid team-analytics-kpis" aria-label="Team analytics summary">
                <div data-metric="completed">
                    <x-dashboard.kpi-card label="Completed" :value="$snapshot->throughput['completed']" help="Selected period" />
                </div>
                <div data-metric="blocked">
                    <x-dashboard.kpi-card label="Blocked now" :value="$snapshot->currentWorkload['blocked']" help="Current workload" />
                </div>
                <div data-metric="overdue">
                    <x-dashboard.kpi-card label="Overdue now" :value="$snapshot->currentWorkload['overdue']" help="Current workload" />
                </div>
                <div data-metric="unassigned">
                    <x-dashboard.kpi-card label="Unassigned now" :value="$snapshot->currentWorkload['unassigned']" help="Current workload" />
                </div>
            </section>

            <div class="team-analytics-grid">
                <section class="dashboard-panel team-analytics-panel" aria-labelledby="team-analytics-status-heading">
                    <div class="dashboard-panel-heading">
                        <div>
                            <p class="planops-eyebrow">Current workload</p>
                            <h2 id="team-analytics-status-heading">Status distribution</h2>
                        </div>
                        <span>Non-cancelled top-level tasks</span>
                    </div>

                    @if ($statusTotal > 0)
                        <div class="team-analytics-status-bar" aria-hidden="true">
                            @foreach ($snapshot->statusDistribution as $status => $count)
                                @if ($count > 0)
                                    <span class="team-analytics-status-segment {{ $statusColors[$status] ?? 'is-neutral' }}" style="--team-analytics-share: {{ round(($count / $statusTotal) * 100, 2) }}%"></span>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <table id="team-analytics-status-table" class="dashboard-data-table">
                        <caption class="sr-only">Current task status distribution for {{ $project->name }}</caption>
                        <thead>
                            <tr><th scope="col">Status</th><th scope="col">Tasks</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshot->statusDistribution as $status => $count)
                                <tr>
                                    <th scope="row">{{ $statusLabels[$status] ?? str($status)->replace('_', ' ')->title() }}</th>
                                    <td>{{ $count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                <section class="dashboard-panel team-analytics-panel" aria-labelledby="team-analytics-flow-heading">
                    <div class="dashboard-panel-heading">
                        <div>
                            <p class="planops-eyebrow">Selected period / flow</p>
                            <h2 id="team-analytics-flow-heading">Throughput by week</h2>
                        </div>
                        <span>Distinct top-level tasks</span>
                    </div>
                    <p class="dashboard-note">Created, completed, and blocked movement in the selected period.</p>

                    <div class="team-analytics-table-wrap" role="region" aria-label="Team analytics throughput by week" tabindex="0">
                        <table id="team-analytics-flow-table" class="dashboard-data-table">
                            <caption class="sr-only">Weekly aggregate work flow for {{ $project->name }}</caption>
                            <thead>
                                <tr><th scope="col">Week</th><th scope="col">Created</th><th scope="col">Completed</th><th scope="col">Blocked</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot->weeklyFlow as $week)
                                    <tr>
                                        <th scope="row">{{ $week['label'] }}</th>
                                        <td>{{ $week['created'] }}</td>
                                        <td>{{ $week['completed'] }}</td>
                                        <td>{{ $week['blocked'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if (! $snapshot->hasRecordedMovement)
                        <p class="team-analytics-empty" role="status">No recorded team movement in this period.</p>
                    @endif
                </section>
            </div>

            <section class="dashboard-panel team-analytics-privacy" data-team-analytics-privacy aria-labelledby="team-analytics-privacy-heading">
                <p class="planops-eyebrow">How to read this</p>
                <h2 id="team-analytics-privacy-heading">Aggregate project data only</h2>
                <p>This view does not show individual metrics, member rankings, or productivity scores. Use Team Work for current workload detail.</p>
            </section>
        </section>
    </div>
</x-app-layout>
