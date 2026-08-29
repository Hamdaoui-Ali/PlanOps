@props(['task', 'project', 'statuses', 'keys', 'columnTasks'])

@php
    $displayKey = $keys->displayKey($task);
    $taskIds = $columnTasks->pluck('id')->all();
    $position = $columnTasks->search(fn ($columnTask): bool => $columnTask->is($task));
    $moveUpIds = $taskIds;
    $moveDownIds = $taskIds;
    if ($position > 0) {
        [$moveUpIds[$position - 1], $moveUpIds[$position]] = [$moveUpIds[$position], $moveUpIds[$position - 1]];
    }
    if ($position < count($taskIds) - 1) {
        [$moveDownIds[$position], $moveDownIds[$position + 1]] = [$moveDownIds[$position + 1], $moveDownIds[$position]];
    }
@endphp

<article class="board-task-card">
    <div class="board-task-card-heading">
        <a href="{{ route('tasks.show', $task) }}" class="board-task-key">{{ $displayKey }}</a>
        <span class="board-task-status">{{ str($task->status->value)->replace('_', ' ')->title() }}</span>
    </div>
    <h3><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></h3>
    <dl class="board-task-meta">
        <div><dt>Priority</dt><dd>{{ str($task->priority->value)->replace('_', ' ')->title() }}</dd></div>
        <div><dt>Due</dt><dd>{{ $task->due_on?->format('M j, Y') ?? 'No due date' }}</dd></div>
        <div><dt>Subtasks</dt><dd>
            @if ($task->eligible_children_count > 0)
                {{ $task->completed_children_count }} of {{ $task->eligible_children_count }} done
            @elseif ($task->children_count > 0)
                No active subtasks
            @else
                None
            @endif
        </dd></div>
    </dl>
    @if ($task->labels->isNotEmpty())
        <div class="board-task-labels" aria-label="Labels for {{ $displayKey }}">
            @foreach ($task->labels as $label)
                <span>{{ $label->name }}</span>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('projects.board.tasks.status', [$project, $task]) }}" class="board-status-form">
        @csrf
        <label for="board-status-{{ $task->id }}">Move {{ $displayKey }} to</label>
        <div class="board-status-controls">
            <select id="board-status-{{ $task->id }}" name="status">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($task->status === $status)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
            <button type="submit" class="planops-button planops-button-secondary">Move</button>
        </div>
    </form>

    <div class="board-reorder-controls">
        <span class="sr-only">Reorder {{ $displayKey }}</span>
        @if ($position > 0)
            <form method="POST" action="{{ route('projects.board.reorder', $project) }}" class="board-reorder-form">
                @csrf
                <input type="hidden" name="status" value="{{ $task->status->value }}">
                @foreach ($moveUpIds as $taskId)
                    <input type="hidden" name="ordered_task_ids[]" value="{{ $taskId }}">
                @endforeach
                <button type="submit" class="board-order-button">Move up</button>
            </form>
        @endif
        @if ($position < count($taskIds) - 1)
            <form method="POST" action="{{ route('projects.board.reorder', $project) }}" class="board-reorder-form">
                @csrf
                <input type="hidden" name="status" value="{{ $task->status->value }}">
                @foreach ($moveDownIds as $taskId)
                    <input type="hidden" name="ordered_task_ids[]" value="{{ $taskId }}">
                @endforeach
                <button type="submit" class="board-order-button">Move down</button>
            </form>
        @endif
    </div>
</article>
