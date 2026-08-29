@props([
    'task',
    'statuses',
    'priorities',
    'saveAction',
    'deleteAction' => null,
])

<section class="task-metadata" aria-labelledby="task-metadata-heading">
    <div class="task-metadata-heading">
        <i class="ph ph-note-pencil" aria-hidden="true"></i>
        <h2 id="task-metadata-heading">Task metadata</h2>
    </div>

    <form id="task-details-form" method="POST" action="{{ $saveAction }}" class="task-metadata-form">
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

        <div class="task-metadata-controls">
            <div class="task-metadata-field">
                <label for="task-status">Status</label>
                <select id="task-status" name="status">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $task->status->value) === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" />
            </div>

            <div class="task-metadata-field">
                <label for="task-priority">Priority</label>
                <select id="task-priority" name="priority">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', $task->priority->value) === $priority->value)>{{ str($priority->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('priority')" />
            </div>

            <div class="task-metadata-field">
                <label for="task-due-on">Due date</label>
                <input id="task-due-on" name="due_on" type="date" value="{{ old('due_on', $task->due_on?->format('Y-m-d')) }}">
                <x-input-error :messages="$errors->get('due_on')" />
            </div>
        </div>

    </form>

    <div class="task-metadata-actions">
        <button type="submit" form="task-details-form" class="planops-button planops-button-primary">
            <i class="ph ph-floppy-disk" aria-hidden="true"></i>
            <span>Save changes</span>
        </button>

        @if ($deleteAction !== null)
            <form method="POST" action="{{ $deleteAction }}" class="task-metadata-delete" onsubmit="return window.confirm('Delete this task?')">
                @csrf
                @method('DELETE')

                <label class="sr-only" for="task-delete-confirmation">Confirm task deletion</label>
                <button id="task-delete-confirmation" type="submit" class="planops-button planops-button-danger">
                    <i class="ph ph-trash" aria-hidden="true"></i>
                    <span>Delete task</span>
                </button>
            </form>
        @endif
    </div>
</section>
