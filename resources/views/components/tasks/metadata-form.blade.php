@props([
    'task',
    'priorities',
    'updateAction',
    'priorityAction',
    'dueDateAction',
    'deleteAction' => null,
])

<section class="task-metadata" aria-labelledby="task-metadata-heading">
    <div class="task-metadata-heading">
        <i class="ph ph-note-pencil" aria-hidden="true"></i>
        <h2 id="task-metadata-heading">Task metadata</h2>
    </div>

    <form method="POST" action="{{ $updateAction }}" class="task-metadata-form">
        @csrf
        @method('PATCH')

        <div class="task-metadata-field">
            <label for="task-title">Title</label>
            <input id="task-title" name="title" type="text" value="{{ old('title', $task->title) }}" required maxlength="300" autocomplete="off">
            <x-input-error :messages="$errors->get('title')" />
        </div>

        <div class="task-metadata-field">
            <label for="task-description">Description</label>
            <textarea id="task-description" name="description" rows="4">{{ old('description', $task->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" />
        </div>

        <button type="submit" class="planops-button planops-button-primary">
            <i class="ph ph-floppy-disk" aria-hidden="true"></i>
            <span>Save details</span>
        </button>
    </form>

    <div class="task-metadata-controls">
        <form method="POST" action="{{ $priorityAction }}" class="task-metadata-form task-metadata-inline-form">
            @csrf
            @method('PATCH')

            <div class="task-metadata-field">
                <label for="task-priority">Priority</label>
                <select id="task-priority" name="priority">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', $task->priority->value) === $priority->value)>{{ str($priority->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('priority')" />
            </div>

            <button type="submit" class="planops-button planops-button-secondary">
                <i class="ph ph-flag" aria-hidden="true"></i>
                <span>Update priority</span>
            </button>
        </form>

        <form method="POST" action="{{ $dueDateAction }}" class="task-metadata-form task-metadata-inline-form">
            @csrf
            @method('PATCH')

            <div class="task-metadata-field">
                <label for="task-due-on">Due date</label>
                <input id="task-due-on" name="due_on" type="date" value="{{ old('due_on', $task->due_on?->format('Y-m-d')) }}">
                <x-input-error :messages="$errors->get('due_on')" />
            </div>

            <button type="submit" class="planops-button planops-button-secondary">
                <i class="ph ph-calendar-check" aria-hidden="true"></i>
                <span>Update due date</span>
            </button>
        </form>
    </div>

    @if ($deleteAction !== null)
        <form method="POST" action="{{ $deleteAction }}" class="task-metadata-delete" onsubmit="return window.confirm('Delete this task?')">
            @csrf
            @method('DELETE')

            <label for="task-delete-confirmation">Confirm task deletion</label>
            <button id="task-delete-confirmation" type="submit" class="planops-button planops-button-danger">
                <i class="ph ph-trash" aria-hidden="true"></i>
                <span>Delete task</span>
            </button>
        </form>
    @endif
</section>
