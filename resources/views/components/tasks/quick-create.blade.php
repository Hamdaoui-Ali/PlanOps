@php
    $hasOptionalErrors = $errors->hasAny(['description', 'status', 'priority', 'due_on', 'parent_task_id']);
@endphp

<form method="POST" action="{{ route('projects.tasks.store', $project) }}" class="task-capture-form">
    @csrf

    <div class="task-capture-field">
        <label for="task-project">Project</label>
        <input id="task-project" type="text" value="{{ $project->key }} — {{ $project->name }}" readonly>
    </div>

    <div class="task-capture-field">
        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="300" autocomplete="off" autofocus>
        <x-input-error :messages="$errors->get('title')" />
    </div>

    <details class="task-capture-details" {{ $hasOptionalErrors ? 'open' : '' }}>
        <summary>More task details</summary>

        <div class="task-capture-details-content">
            <div class="task-capture-field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <div class="task-capture-field-grid">
                <div class="task-capture-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'NOT_STARTED') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('status')" />
                </div>

                <div class="task-capture-field">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'MEDIUM') === $priority->value)>{{ str($priority->value)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('priority')" />
                </div>
            </div>

            <div class="task-capture-field-grid">
                <div class="task-capture-field">
                    <label for="due_on">Due date</label>
                    <input id="due_on" name="due_on" type="date" value="{{ old('due_on') }}">
                    <x-input-error :messages="$errors->get('due_on')" />
                </div>

                <div class="task-capture-field">
                    <label for="parent_task_id">Parent task</label>
                    <select id="parent_task_id" name="parent_task_id">
                        <option value="">No parent task</option>
                        @foreach ($parentOptions as $parentOption)
                            <option value="{{ $parentOption['id'] }}" @selected((string) old('parent_task_id') === (string) $parentOption['id'])>{{ $parentOption['display_key'] }} — {{ $parentOption['title'] }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('parent_task_id')" />
                </div>
            </div>
        </div>
    </details>

    <div class="task-capture-actions">
        <button type="submit" class="planops-button planops-button-primary">
            <i class="ph ph-plus" aria-hidden="true"></i>
            <span>Create task</span>
        </button>
        <a href="{{ route('projects.edit', $project) }}" class="task-capture-cancel">Cancel</a>
    </div>
</form>
