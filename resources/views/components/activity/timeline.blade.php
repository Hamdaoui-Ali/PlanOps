@props(['activities', 'keys'])

@php
    $formatValue = static function (mixed $value): string {
        if (is_array($value)) {
            return collect($value)->map(fn (mixed $item, string|int $key): string => str($item)->replace('_', ' ')->title())->implode(', ');
        }

        return $value === null || $value === '' ? '—' : str((string) $value)->replace('_', ' ')->title();
    };
@endphp

<ol class="activity-timeline" aria-label="Activity events">
    @foreach ($activities as $activity)
        @php($task = $activity->task)
        <li class="activity-timeline-item">
            <div class="activity-timeline-marker" aria-hidden="true"><i class="ph ph-waveform"></i></div>
            <div class="activity-timeline-content">
                <div class="activity-timeline-heading">
                    <strong>{{ str($activity->event_type->value)->replace('_', ' ')->title() }}</strong>
                    <time datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->format('M j, Y · H:i') }}</time>
                </div>
                @if ($task)
                    <p><a href="{{ route('tasks.show', $task) }}">{{ $keys->displayKey($task) }} · {{ $task->title }}</a><span> in {{ $activity->project?->name }}</span></p>
                @else
                    <p>Historical task context unavailable.</p>
                @endif
                @if ($activity->field)
                    <p class="activity-timeline-change"><span>{{ str($activity->field)->replace('_', ' ')->title() }}</span>: {{ $formatValue($activity->old_value) }} <span aria-hidden="true">→</span> {{ $formatValue($activity->new_value) }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
