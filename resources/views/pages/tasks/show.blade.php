<x-app-layout>
    @php
        $formatValue = static function (mixed $value): string {
            if (is_array($value)) {
                return collect($value)->map(fn (mixed $item, string|int $key): string => str($key)->replace('_', ' ')->title().' '.(is_array($item) ? json_encode($item) : $item))->implode(', ');
            }

            return $value === null || $value === '' ? '—' : (string) $value;
        };
    @endphp

    <div class="planops-console">
        <section class="task-detail-page" aria-labelledby="task-detail-heading">
            @if (session('status'))
                <div class="planops-flash" role="status">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <header class="task-detail-header">
                <div>
                    <a href="{{ route('projects.show', $task->project) }}" class="task-detail-back">← Back to {{ $task->project->name }}</a>
                    <p class="planops-eyebrow">{{ $displayKey }}</p>
                    <h1 id="task-detail-heading">{{ $task->title }}</h1>
                    <p>Task details and recorded changes for this work item.</p>
                </div>
            </header>

            <div class="task-detail-grid">
                <div>
                    <x-tasks.metadata-form
                        :task="$task"
                        :priorities="$priorities"
                        :update-action="route('tasks.update', $task)"
                        :priority-action="route('tasks.priority', $task)"
                        :due-date-action="route('tasks.due-date', $task)"
                        :delete-action="route('tasks.destroy', $task)"
                    />
                </div>

                <aside class="task-detail-aside">
                    <section class="task-detail-panel" aria-labelledby="task-status-heading">
                        <p class="planops-eyebrow">Workflow</p>
                        <h2 id="task-status-heading">Status</h2>
                        <form method="POST" action="{{ route('tasks.status', $task) }}" class="task-detail-status-form">
                            @csrf
                            <label for="task-detail-status">Task status</label>
                            <select id="task-detail-status" name="status">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($task->status === $status)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="planops-button planops-button-secondary">Save status</button>
                        </form>
                    </section>

                    <section class="task-detail-panel" aria-labelledby="task-subtasks-heading">
                        <p class="planops-eyebrow">Breakdown</p>
                        <h2 id="task-subtasks-heading">Subtasks</h2>
                        @if ($task->children->isEmpty())
                            <p class="task-detail-muted">No subtasks yet.</p>
                        @else
                            <ul class="task-detail-subtasks">
                                @foreach ($task->children as $child)
                                    <li>
                                        <a href="{{ route('tasks.show', $child) }}">{{ $task->project->key }}-{{ $child->number }} · {{ $child->title }}</a>
                                        <span>{{ str($child->status->value)->replace('_', ' ')->title() }} · {{ str($child->priority->value)->replace('_', ' ')->title() }} · {{ $child->due_on?->format('M j, Y') ?? 'No due date' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </aside>
            </div>

            <section class="task-detail-panel task-detail-activity" aria-labelledby="task-activity-heading">
                <p class="planops-eyebrow">History</p>
                <h2 id="task-activity-heading">Activity</h2>
                @if ($activities->isEmpty())
                    <p class="task-detail-muted">No activity recorded yet.</p>
                @else
                    <ol class="task-detail-timeline">
                        @foreach ($activities as $activity)
                            <li>
                                <strong>{{ str($activity->event_type->value)->replace('_', ' ')->title() }}</strong>
                                @if ($activity->field)
                                    <span>{{ str($activity->field)->replace('_', ' ')->title() }}: {{ $formatValue($activity->old_value) }} → {{ $formatValue($activity->new_value) }}</span>
                                @endif
                                <time datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->format('M j, Y · H:i') }}</time>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </section>
    </div>
</x-app-layout>
