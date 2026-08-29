<x-app-layout>
    @php
        $progress = min(max((float) $project->progress_percent, 0), 100);
        $progressLabel = $progress === (float) (int) $progress ? (int) $progress : $progress;
    @endphp

    <div class="planops-console">
        <section class="project-overview-page" aria-labelledby="project-overview-heading">
            @if (session('status'))
                <div class="planops-flash" role="status">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <header class="project-overview-header">
                <div>
                    <p class="planops-eyebrow">Project / overview</p>
                    <div class="project-overview-title-row">
                        <h1 id="project-overview-heading">{{ $project->name }}</h1>
                        <span class="project-key">{{ $project->key }}</span>
                    </div>
                    <p>{{ $project->description ?: 'Track the work that moves this project forward.' }}</p>
                </div>
                <div class="project-overview-actions">
                    <a href="{{ route('projects.board', $project) }}" class="planops-button planops-button-secondary">Open board</a>
                    <a href="{{ route('projects.edit', $project) }}" class="planops-button planops-button-secondary">Edit project</a>
                    <a href="{{ route('projects.tasks.create', $project) }}" class="planops-button planops-button-primary">
                        <i class="ph ph-plus" aria-hidden="true"></i>
                        <span>New task</span>
                    </a>
                </div>
            </header>

            <section class="project-overview-summary" aria-labelledby="project-progress-heading">
                <div>
                    <p class="planops-eyebrow">{{ str($project->status->value)->replace('_', ' ')->title() }}</p>
                    <h2 id="project-progress-heading">Progress</h2>
                    <p>{{ $project->completed_task_count }} of {{ $project->eligible_task_count }} tasks done</p>
                    <p class="project-progress-help">Progress uses completed top-level tasks. Cancelled tasks and subtasks are excluded.</p>
                </div>
                <div class="project-overview-progress-value" aria-label="{{ $project->name }} progress is {{ $progressLabel }} percent">
                    <strong>{{ $progressLabel }}%</strong>
                    @if ($project->eligible_task_count === 0)
                        <span>No active scope</span>
                    @endif
                </div>
                <div class="project-progress-track" role="progressbar" aria-label="{{ $project->name }} progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
                    <span class="project-progress-fill" style="width: {{ $progress }}%"></span>
                </div>
            </section>

            <section class="project-overview-tasks" aria-labelledby="project-tasks-heading">
                <div class="project-overview-section-heading">
                    <div>
                        <p class="planops-eyebrow">Project work</p>
                        <h2 id="project-tasks-heading">Tasks</h2>
                    </div>
                    <span>{{ $project->tasks->count() }} top-level {{ str('task')->plural($project->tasks->count()) }}</span>
                </div>

                @if ($project->tasks->isEmpty())
                    <div class="projects-empty-state" role="status">
                        <i class="ph ph-list-checks" aria-hidden="true"></i>
                        <h3>This project has no tracked work yet.</h3>
                        <p>Add the first task to start measuring progress.</p>
                        <a href="{{ route('projects.tasks.create', $project) }}" class="planops-button planops-button-primary">New task</a>
                    </div>
                @else
                    <div class="projects-ledger-wrap">
                        <table class="projects-ledger project-tasks-table">
                            <caption class="sr-only">Tasks for {{ $project->name }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Task</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Due</th>
                                    <th scope="col">Subtasks</th>
                                    <th scope="col"><span class="sr-only">Save status</span></th>
                                </tr>
                            </thead>
                            @foreach ($project->tasks as $task)
                                <tbody x-data="{ open: false }">
                                    <tr>
                                        <th scope="row" class="project-identity">
                                            <div class="task-parent-identity">
                                                @if ($task->children_count > 0)
                                                    <button type="button" class="task-subtasks-toggle" aria-expanded="false" aria-controls="subtasks-{{ $task->id }}" data-subtasks-toggle="subtasks-{{ $task->id }}" @click="open = ! open" :aria-expanded="open.toString()">
                                                        <i class="ph ph-caret-right" aria-hidden="true" :class="{ 'rotate-90': open }"></i>
                                                        <span x-text="open ? 'Hide subtasks' : 'Show subtasks'">Show subtasks</span>
                                                    </button>
                                                @endif
                                                <a href="{{ route('tasks.show', $task) }}" class="project-name">{{ $task->title }}</a>
                                                <span class="project-key">{{ $project->key }}-{{ $task->number }}</span>
                                            </div>
                                        </th>
                                        <td>{{ str($task->status->value)->replace('_', ' ')->title() }}</td>
                                        <td>{{ str($task->priority->value)->replace('_', ' ')->title() }}</td>
                                        <td>{{ $task->due_on?->format('M j, Y') ?? 'No due date' }}</td>
                                        <td>
                                            @if ($task->eligible_children_count > 0)
                                                {{ $task->completed_children_count }} of {{ $task->eligible_children_count }} done
                                            @elseif ($task->children_count > 0)
                                                <span class="project-no-scope">No active subtasks</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('tasks.status', $task) }}" class="task-status-form">
                                                @csrf
                                                <label class="sr-only" for="task-status-{{ $task->id }}">Status for {{ $task->title }}</label>
                                                <select id="task-status-{{ $task->id }}" name="status">
                                                    @foreach ($statuses as $status)
                                                        <option value="{{ $status->value }}" @selected($task->status === $status)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="planops-button planops-button-secondary">Save status</button>
                                            </form>
                                        </td>
                                    </tr>
                                    @if ($task->children_count > 0)
                                        <tr id="subtasks-{{ $task->id }}" class="task-subtasks-row" hidden x-bind:hidden="! open">
                                            <td colspan="6">
                                                <div class="task-subtasks-panel">
                                                    @foreach ($task->children as $subtask)
                                                        <a href="{{ route('tasks.show', $subtask) }}" class="task-subtask-row">
                                                            <span class="project-key">{{ $project->key }}-{{ $subtask->number }}</span>
                                                            <span class="task-subtask-title">{{ $subtask->title }}</span>
                                                            <span>{{ str($subtask->status->value)->replace('_', ' ')->title() }}</span>
                                                            <span>{{ str($subtask->priority->value)->replace('_', ' ')->title() }}</span>
                                                            <span>{{ $subtask->due_on?->format('M j, Y') ?? 'No due date' }}</span>
                                                            <span class="task-subtask-edit">Edit</span>
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                @endif
            </section>
        </section>
    </div>
</x-app-layout>
