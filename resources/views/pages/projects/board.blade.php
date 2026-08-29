<x-app-layout>
    <div class="planops-console">
        <section class="project-board-page" aria-labelledby="project-board-heading">
            @if (session('status'))
                <div class="planops-flash" role="status">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <header class="project-board-header">
                <div>
                    <a href="{{ route('projects.show', $project) }}" class="board-back-link">Back to overview</a>
                    <p class="planops-eyebrow">Project / board</p>
                    <div class="project-overview-title-row">
                        <h1 id="project-board-heading">{{ $project->name }}</h1>
                        <span class="project-key">{{ $project->key }}</span>
                    </div>
                    <p>Move work through the workflow and keep the order that helps you focus.</p>
                </div>
                <a href="{{ route('projects.tasks.create', $project) }}" class="planops-button planops-button-primary">
                    <i class="ph ph-plus" aria-hidden="true"></i>
                    <span>New task</span>
                </a>
            </header>

            <form method="GET" action="{{ route('projects.board', $project) }}" class="board-filter-form">
                <label for="include-cancelled">Show cancelled tasks</label>
                <input id="include-cancelled" type="checkbox" name="include_cancelled" value="1" @checked($includeCancelled)>
                <button type="submit" class="planops-button planops-button-secondary">Apply filter</button>
            </form>

            @php($hasTasks = collect($columns)->flatten(1)->isNotEmpty())
            @if (! $hasTasks)
                <div class="projects-empty-state board-empty-state" role="status">
                    <i class="ph ph-kanban" aria-hidden="true"></i>
                    <h2>No board tasks yet.</h2>
                    <p>Add a task to start moving work through the project workflow.</p>
                    <a href="{{ route('projects.tasks.create', $project) }}" class="planops-button planops-button-primary">New task</a>
                </div>
            @endif

            <div class="project-board-grid">
                @foreach ($columns as $statusValue => $columnTasks)
                    @php($status = \App\Domain\Tasks\Enums\TaskStatus::from($statusValue))
                    <section class="board-column" aria-labelledby="board-column-{{ strtolower($statusValue) }}">
                        <header class="board-column-header">
                            <div>
                                <p class="planops-eyebrow">{{ $columnTasks->count() }} {{ str('task')->plural($columnTasks->count()) }}</p>
                                <h2 id="board-column-{{ strtolower($statusValue) }}">{{ str($statusValue)->replace('_', ' ')->title() }}</h2>
                            </div>
                        </header>
                        @if ($columnTasks->isEmpty())
                            <p class="board-column-empty">No tasks in this status.</p>
                        @else
                            <div class="board-column-cards">
                                @foreach ($columnTasks as $task)
                                    <x-tasks.task-card :task="$task" :project="$project" :statuses="$statuses" :keys="$keys" :column-tasks="$columnTasks" />
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
