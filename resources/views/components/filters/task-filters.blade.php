@props(['filters', 'projects', 'labels', 'statuses', 'priorities'])

<form method="GET" action="{{ route('my-work') }}" class="my-work-filters" role="search">
    <div class="my-work-filter-grid">
        <div class="my-work-filter-field">
            <label for="my-work-project">Project</label>
            <select id="my-work-project" name="project">
                <option value="">All projects</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) ($filters['project'] ?? '') === (string) $project->id)>{{ $project->name }} ({{ $project->key }})</option>
                @endforeach
            </select>
        </div>
        <div class="my-work-filter-field">
            <label for="my-work-status">Status</label>
            <select id="my-work-status" name="status">
                <option value="">Focus statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <div class="my-work-filter-field">
            <label for="my-work-priority">Priority</label>
            <select id="my-work-priority" name="priority">
                <option value="">All priorities</option>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ str($priority->value)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </div>
        <div class="my-work-filter-field">
            <label for="my-work-label">Label</label>
            <select id="my-work-label" name="label">
                <option value="">All labels</option>
                @foreach ($labels as $label)
                    <option value="{{ $label->id }}" @selected((string) ($filters['label'] ?? '') === (string) $label->id)>{{ $label->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="my-work-filter-field">
            <label for="my-work-due">Due</label>
            <select id="my-work-due" name="due">
                <option value="">Any due date</option>
                @foreach (['overdue' => 'Overdue', 'today' => 'Due today', 'this_week' => 'Due this week', 'no_due_date' => 'No due date'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['due'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="my-work-filter-field">
            <label for="my-work-sort">Sort</label>
            <select id="my-work-sort" name="sort">
                @foreach (['updated' => 'Recently updated', 'created' => 'Recently created', 'priority' => 'Priority', 'due' => 'Due date', 'task_key' => 'Task key', 'project' => 'Project'] as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'updated') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <details class="my-work-date-filters">
        <summary>Created and updated dates</summary>
        <div class="my-work-date-grid">
            <div class="my-work-filter-field"><label for="created-from">Created from</label><input id="created-from" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></div>
            <div class="my-work-filter-field"><label for="created-until">Created until</label><input id="created-until" type="date" name="created_until" value="{{ $filters['created_until'] ?? '' }}"></div>
            <div class="my-work-filter-field"><label for="updated-from">Updated from</label><input id="updated-from" type="date" name="updated_from" value="{{ $filters['updated_from'] ?? '' }}"></div>
            <div class="my-work-filter-field"><label for="updated-until">Updated until</label><input id="updated-until" type="date" name="updated_until" value="{{ $filters['updated_until'] ?? '' }}"></div>
        </div>
    </details>
    <div class="my-work-filter-actions">
        <button type="submit" class="planops-button planops-button-primary">Apply filters</button>
        @if (count($filters) > 0)
            <a href="{{ route('my-work') }}" class="planops-button planops-button-secondary">Reset filters</a>
        @endif
    </div>
</form>
