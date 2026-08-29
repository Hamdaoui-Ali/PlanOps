@props(['tasks', 'filters', 'keys', 'project', 'statuses', 'priorities'])

@php($returnQuery = http_build_query(['return_context' => 'project-tasks', 'project' => $project->getKey(), ...$filters]))
<div class="my-work-table-wrap project-task-table-wrap">
    <table class="my-work-table project-task-table">
        <caption class="sr-only">Tasks for {{ $project->name }}</caption>
        <thead><tr><th scope="col">Task</th><th scope="col">Status</th><th scope="col">Priority</th><th scope="col">Due</th><th scope="col">Labels</th><th scope="col">Subtasks</th><th scope="col">Updated</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
            @foreach ($tasks as $task)
                @php($displayKey = $keys->displayKey($task))
                <tr @class(['project-task-subtask' => $task->parent_task_id !== null])>
                    <th scope="row" data-label="Task"><div class="project-task-identity"><a href="{{ route('tasks.show', $task) }}" class="my-work-task-key">{{ $displayKey }}</a><a href="{{ route('tasks.show', $task) }}" class="my-work-task-title">{{ $task->title }}</a>@if ($task->parent)<span class="project-task-parent">Subtask of {{ $keys->displayKey($task->parent) }}</span>@endif</div></th>
                    <td data-label="Status">{{ str($task->status->value)->replace('_', ' ')->title() }}</td>
                    <td data-label="Priority">{{ str($task->priority->value)->replace('_', ' ')->title() }}</td>
                    <td data-label="Due">{{ $task->due_on?->format('M j, Y') ?? 'No due date' }}</td>
                    <td data-label="Labels">@forelse ($task->labels as $label)<span class="my-work-label">{{ $label->name }}</span>@empty<span class="my-work-muted">None</span>@endforelse</td>
                    <td data-label="Subtasks">@if ($task->eligible_children_count > 0){{ $task->completed_children_count }} of {{ $task->eligible_children_count }} done @elseif ($task->children_count > 0)No active subtasks @else None @endif</td>
                    <td data-label="Updated"><time datetime="{{ $task->updated_at?->toIso8601String() }}">{{ $task->updated_at?->format('M j, Y · H:i') }}</time></td>
                    <td data-label="Actions"><div class="my-work-actions"><form method="POST" action="{{ route('tasks.status', $task) }}?{{ $returnQuery }}" class="my-work-quick-form">@csrf<label for="project-task-status-{{ $task->id }}">Change status for {{ $displayKey }}</label><select id="project-task-status-{{ $task->id }}" name="status">@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected($task->status === $status)>{{ str($status->value)->replace('_', ' ')->title() }}</option>@endforeach</select><button type="submit" class="planops-button planops-button-secondary">Save</button></form><form method="POST" action="{{ route('tasks.priority', $task) }}?{{ $returnQuery }}" class="my-work-quick-form">@csrf @method('PATCH')<label for="project-task-priority-{{ $task->id }}">Change priority for {{ $displayKey }}</label><select id="project-task-priority-{{ $task->id }}" name="priority">@foreach ($priorities as $priority)<option value="{{ $priority->value }}" @selected($task->priority === $priority)>{{ str($priority->value)->replace('_', ' ')->title() }}</option>@endforeach</select><button type="submit" class="planops-button planops-button-secondary">Save</button></form></div></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
