<x-app-layout>
    <div class="planops-console"><section class="activity-page" aria-labelledby="activity-heading">
        <header class="my-work-header"><div><p class="planops-eyebrow">Workspace / history</p><h1 id="activity-heading">Activity</h1><p>A chronological record of changes across your projects and tasks.</p></div><a href="{{ route('my-work') }}" class="planops-button planops-button-secondary">View My Work</a></header>
        @if (session('status'))<div class="planops-flash" role="status"><i class="ph ph-check-circle" aria-hidden="true"></i><span>{{ session('status') }}</span></div>@endif
        <form method="GET" action="{{ route('activity') }}" class="my-work-filters activity-filters" role="search">
            <div class="my-work-filter-grid">
                <div class="my-work-filter-field"><label for="activity-project">Project</label><select id="activity-project" name="project_id"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->name }} ({{ $project->key }})</option>@endforeach</select></div>
                <div class="my-work-filter-field"><label for="activity-task">Task</label><select id="activity-task" name="task_id"><option value="">All tasks</option>@foreach ($tasks as $task)<option value="{{ $task->id }}" @selected((string) ($filters['task_id'] ?? '') === (string) $task->id)>{{ $task->project?->key }}-{{ $task->number }} · {{ $task->title }}</option>@endforeach</select></div>
                <div class="my-work-filter-field"><label for="activity-type">Event type</label><select id="activity-type" name="event_type"><option value="">All events</option>@foreach ($eventTypes as $eventType)<option value="{{ $eventType->value }}" @selected(($filters['event_type'] ?? '') === $eventType->value)>{{ str($eventType->value)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                <div class="my-work-filter-field"><label for="activity-from">From</label><input id="activity-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
                <div class="my-work-filter-field"><label for="activity-until">Until</label><input id="activity-until" type="date" name="until" value="{{ $filters['until'] ?? '' }}"></div>
            </div>
            <div class="my-work-filter-actions"><button type="submit" class="planops-button planops-button-primary">Apply filters</button>@if (count($filters) > 0)<a href="{{ route('activity') }}" class="planops-button planops-button-secondary">Reset filters</a>@endif</div>
        </form>
        @if ($activities->total() === 0)
            <div class="projects-empty-state my-work-empty" role="status"><i class="ph ph-waveform" aria-hidden="true"></i><h2>No activity recorded yet.</h2><p>Changes to your projects and tasks will appear here.</p>@if (count($filters) > 0)<a href="{{ route('activity') }}" class="planops-button planops-button-primary">Clear filters</a>@endif</div>
        @else
            <x-activity.timeline :activities="$activities" :keys="$keys" />
            @if ($activities->hasPages())<nav class="my-work-pagination" aria-label="Activity pages">{{ $activities->links() }}</nav>@endif
        @endif
    </section></div>
</x-app-layout>
