@props(['items', 'type'])

<ul class="search-result-list" aria-label="{{ $type }} results">
    @foreach ($items as $item)
        <li class="search-result-item">
            @if ($type === 'Tasks')
                <a href="{{ route('tasks.show', $item) }}" class="search-result-link">
                    <span class="search-result-key">{{ $item->project->key }}-{{ $item->number }}</span>
                    <span class="search-result-title">{{ $item->title }}</span>
                    <span class="search-result-meta">{{ str($item->status->value)->replace('_', ' ')->title() }} · {{ $item->project->name }}</span>
                </a>
            @else
                <a href="{{ route('projects.show', $item) }}" class="search-result-link">
                    <span class="search-result-key">{{ $item->key }}</span>
                    <span class="search-result-title">{{ $item->name }}</span>
                    <span class="search-result-meta">{{ str($item->status->value)->replace('_', ' ')->title() }}</span>
                </a>
            @endif
        </li>
    @endforeach
</ul>
