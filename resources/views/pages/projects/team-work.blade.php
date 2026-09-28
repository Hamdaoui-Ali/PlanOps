<x-app-layout>
    <div class="planops-console">
        <section class="project-overview-page" aria-labelledby="team-work-heading">
            <header class="project-overview-header">
                <div>
                    <p class="planops-eyebrow">Project / workload</p>
                    <h1 id="team-work-heading">Team Work</h1>
                    <p>{{ $snapshot->project->name }} workload visibility for project managers.</p>
                </div>
                <a href="{{ route('projects.team', $snapshot->project) }}" class="planops-button planops-button-secondary">Team</a>
            </header>
        </section>
    </div>
</x-app-layout>
