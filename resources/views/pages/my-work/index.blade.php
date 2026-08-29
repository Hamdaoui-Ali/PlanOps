<x-app-layout>
    <div class="planops-console">
        <section class="my-work-page" aria-labelledby="my-work-heading">
            <header class="my-work-header">
                <div>
                    <p class="planops-eyebrow">Workspace / execution</p>
                    <h1 id="my-work-heading">My Work</h1>
                    <p>Focus on the work that is moving, waiting, blocked, or ready to start.</p>
                </div>
                <a href="{{ route('projects.index') }}" class="planops-button planops-button-secondary">View projects</a>
            </header>

            @if (session('status'))
                <div class="planops-flash" role="status"><i class="ph ph-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>
            @endif

            <x-filters.task-filters :filters="$filters" :projects="$projects" :labels="$labels" :statuses="$statuses" :priorities="$priorities" />

            @if ($tasks->total() === 0)
                <div class="projects-empty-state my-work-empty" role="status">
                    <i class="ph ph-clipboard-text" aria-hidden="true"></i>
                    @if ($hasAnyTasks && count($filters) > 0)
                        <h2>No tasks match these filters.</h2>
                        <p>Clear the filters to return to your current focus.</p>
                        <a href="{{ route('my-work') }}" class="planops-button planops-button-primary">Reset filters</a>
                    @else
                        <h2>No tracked work yet.</h2>
                        <p>Create a project and task to start tracking work here.</p>
                        <a href="{{ route('projects.index') }}" class="planops-button planops-button-primary">View projects</a>
                    @endif
                </div>
            @endif

            @php($visibleStatuses = isset($filters['status']) ? [\App\Domain\Tasks\Enums\TaskStatus::from($filters['status'])] : $focusStatuses)
            <div class="my-work-sections">
                @foreach ($visibleStatuses as $status)
                    @php($sectionTasks = $tasks->getCollection()->filter(fn ($task): bool => $task->status === $status)->values())
                    <section class="my-work-section" aria-labelledby="my-work-section-{{ strtolower($status->value) }}">
                        <header class="my-work-section-heading">
                            <div><p class="planops-eyebrow">{{ $sectionTasks->count() }} {{ str('task')->plural($sectionTasks->count()) }}</p><h2 id="my-work-section-{{ strtolower($status->value) }}">{{ str($status->value)->replace('_', ' ')->title() }}</h2></div>
                        </header>
                        @if ($sectionTasks->isEmpty())
                            <p class="my-work-section-empty">No tasks in this status.</p>
                        @else
                            <x-tasks.task-table :tasks="$sectionTasks" :filters="$filters" :keys="$keys" />
                        @endif
                    </section>
                @endforeach
            </div>

            @if ($tasks->hasPages())
                <nav class="my-work-pagination" aria-label="My Work pages">{{ $tasks->links() }}</nav>
            @endif
        </section>
    </div>
</x-app-layout>
