<x-app-layout>
    @php
        $statuses = \App\Domain\Projects\Enums\ProjectStatus::cases();
        $currentFilters = request()->only(['search', 'status', 'target_date', 'sort']);
        $archiveUrl = static function (string $value) use ($currentFilters): string {
            return route('projects.index', array_filter(
                [...$currentFilters, 'archived' => $value],
                static fn (mixed $filter): bool => $filter !== null && $filter !== '',
            ));
        };
        $isFiltered = request()->filled('search')
            || request()->filled('status')
            || request()->filled('target_date')
            || request()->filled('archived');
    @endphp

    <div class="planops-console">
        <section class="projects-page" aria-labelledby="projects-heading">
            @if (session('status'))
                <div class="planops-flash" role="status">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <header class="projects-header">
                <div>
                    <p class="planops-eyebrow">Workspace / ledger</p>
                    <h1 id="projects-heading">Projects</h1>
                    <p class="projects-intro">A focused view of the work you own and the outcomes you are moving forward.</p>
                </div>

                <a href="{{ route('projects.create') }}" class="planops-button planops-button-primary">
                    <i class="ph ph-plus" aria-hidden="true"></i>
                    <span>New project</span>
                </a>
            </header>

            <form method="GET" action="{{ route('projects.index') }}" class="projects-toolbar" role="search">
                <div class="projects-search-field">
                    <label for="project-search">Find a project</label>
                    <div class="projects-search-control">
                        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        <input
                            id="project-search"
                            name="search"
                            type="search"
                            value="{{ request('search') }}"
                            placeholder="Find a project"
                            autocomplete="off"
                        >
                    </div>
                </div>

                <input type="hidden" name="archived" value="{{ request('archived', 'active') }}">

                <div class="projects-filter-field">
                    <label for="project-status">Status</label>
                    <select id="project-status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->value }}
                            </option>
                        @endforeach
                    </select>
                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                </div>

                <div class="projects-filter-field">
                    <label for="project-target-date">Target date</label>
                    <select id="project-target-date" name="target_date">
                        <option value="">Any target date</option>
                        <option value="overdue" @selected(request('target_date') === 'overdue')>Overdue</option>
                        <option value="no_target" @selected(request('target_date') === 'no_target')>No target date</option>
                    </select>
                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                </div>

                <div class="projects-filter-field">
                    <label for="project-sort">Sort by</label>
                    <select id="project-sort" name="sort">
                        <option value="updated" @selected(request('sort', 'updated') === 'updated')>Recently updated</option>
                        <option value="name" @selected(request('sort') === 'name')>Name</option>
                        <option value="progress" @selected(request('sort') === 'progress')>Progress</option>
                        <option value="target_on" @selected(request('sort') === 'target_on')>Target date</option>
                        <option value="created" @selected(request('sort') === 'created')>Creation date</option>
                    </select>
                    <i class="ph ph-caret-down" aria-hidden="true"></i>
                </div>

                <button type="submit" class="planops-button planops-button-secondary">
                    <i class="ph ph-faders" aria-hidden="true"></i>
                    <span>Apply filters</span>
                </button>
            </form>

            <div class="projects-view-switcher" aria-label="Project archive view">
                <a href="{{ $archiveUrl('active') }}" @class(['is-selected' => request('archived', 'active') === 'active']) @if (request('archived', 'active') === 'active') aria-current="page" @endif>
                    Active
                </a>
                <a href="{{ $archiveUrl('archived') }}" @class(['is-selected' => request('archived') === 'archived']) @if (request('archived') === 'archived') aria-current="page" @endif>
                    Archived
                </a>
                <a href="{{ $archiveUrl('all') }}" @class(['is-selected' => request('archived') === 'all']) @if (request('archived') === 'all') aria-current="page" @endif>
                    All
                </a>
            </div>

            <p class="projects-progress-note">
                Progress is calculated from completed top-level tasks. Cancelled tasks and subtasks are excluded.
            </p>

            @if ($projects->isNotEmpty())
                <div class="projects-ledger-wrap">
                    <table class="projects-ledger">
                        <caption class="sr-only">Projects owned by {{ auth()->user()->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Project name</th>
                                <th scope="col">Status</th>
                                <th scope="col">Tasks</th>
                                <th scope="col">Progress</th>
                                <th scope="col">Target date</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($projects as $project)
                                @php
                                    $progress = (float) $project->progress_percent;
                                    $progressLabel = $progress === (float) (int) $progress ? (int) $progress : $progress;
                                    $statusLabel = $project->status->value;
                                @endphp
                                <tr class="project-row" data-status="{{ strtolower($statusLabel) }}">
                                    <th scope="row" class="project-identity">
                                        <a href="{{ route('projects.show', $project) }}" class="project-name">
                                            {{ $project->name }}
                                        </a>
                                        <span class="project-key">{{ $project->key }}</span>
                                    </th>
                                    <td>
                                        <span class="project-status" data-status="{{ strtolower($statusLabel) }}">
                                            <span class="project-status-dot" aria-hidden="true"></span>
                                            <span>{{ $statusLabel }}</span>
                                        </span>
                                    </td>
                                    <td class="project-scope">
                                        @if ($project->eligible_task_count > 0)
                                            <span>{{ $project->completed_task_count }} of {{ $project->eligible_task_count }} done</span>
                                        @else
                                            <span class="project-no-scope">No active scope</span>
                                        @endif
                                    </td>
                                    <td class="project-percent">
                                        <span>{{ $progressLabel }}%</span>
                                        <span
                                            class="project-progress-track"
                                            role="progressbar"
                                            aria-label="{{ $project->name }} progress"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                            aria-valuenow="{{ $progress }}"
                                        >
                                            <span class="project-progress-fill" style="width: {{ min(max($progress, 0), 100) }}%"></span>
                                        </span>
                                    </td>
                                    <td class="project-target">
                                        @if ($project->target_on)
                                            <span class="project-target-value">
                                                <i class="ph ph-target" aria-hidden="true"></i>
                                                <span>{{ $project->target_on->format('M j, Y') }}</span>
                                            </span>
                                        @else
                                            <span class="project-target-value is-empty">
                                                <i class="ph ph-target" aria-hidden="true"></i>
                                                <span>No target date</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="project-action">
                                        <a href="{{ route('projects.show', $project) }}" class="project-open">
                                            <i class="ph ph-arrow-up-right" aria-hidden="true"></i>
                                            <span>Open project</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($projects->hasPages())
                    <nav class="projects-pagination" aria-label="Projects pagination">
                        {{ $projects->links() }}
                    </nav>
                @endif
            @else
                <div class="projects-empty-state" role="status">
                    <i class="ph ph-folder-open" aria-hidden="true"></i>
                    @if ($isFiltered)
                        <h2>No projects match your current filters.</h2>
                        <p>Try a different search or clear the filters to see your full project ledger.</p>
                        <a href="{{ route('projects.index') }}" class="planops-button planops-button-secondary">Reset filters</a>
                    @else
                        <h2>Create your first project to start organizing work.</h2>
                        <p>Give a meaningful outcome a home, then add the work that moves it forward.</p>
                        <a href="{{ route('projects.create') }}" class="planops-button planops-button-primary">New project</a>
                    @endif
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
