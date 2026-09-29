<x-app-layout>
    <div class="planops-console">
        <section class="analytics-page" aria-labelledby="team-analytics-heading">
            <header class="dashboard-header">
                <div>
                    <p class="planops-eyebrow">Project / team measurement</p>
                    <h1 id="team-analytics-heading">{{ $project->name }} Team Analytics</h1>
                    <p>{{ $project->key }} - Aggregate project flow for Owners and Admins.</p>
                </div>
                <a href="{{ route('projects.show', $project) }}" class="planops-button planops-button-secondary">Project overview</a>
            </header>

            <x-dashboard.period-selector :selection="$selection" :action="route('projects.team.analytics', $project)" />
            <section class="dashboard-period-banner">
                <span class="planops-eyebrow">Selected period</span>
                <strong>{{ $snapshot->reportPeriod->label }}</strong>
            </section>
        </section>
    </div>
</x-app-layout>
