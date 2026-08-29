<x-app-layout>
    <div class="planops-console"><section class="search-page" aria-labelledby="search-heading">
        <header class="my-work-header"><div><p class="planops-eyebrow">Workspace / discovery</p><h1 id="search-heading">Search</h1><p>Find projects and tasks by key, title, description, or label.</p></div></header>
        <form method="GET" action="{{ route('search') }}" class="global-search-form" role="search" x-data x-ref="searchForm">
            <label for="search-query">Search projects and tasks</label>
            <div class="global-search-control"><input id="search-query" name="q" type="search" value="{{ $term }}" minlength="2" maxlength="100" placeholder="Try PLAN-5 or onboarding" autocomplete="off" autofocus><button type="submit" class="planops-button planops-button-primary">Search</button></div>
            <p class="search-help" id="search-help">Press <kbd>/</kbd> from anywhere to focus search.</p>
        </form>
        @if (! $searched)
            <div class="projects-empty-state my-work-empty" role="status"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><h2>Search your workspace.</h2><p>Enter at least 2 characters to find matching projects and tasks.</p></div>
        @elseif (mb_strlen($term) < 2)
            <div class="projects-empty-state my-work-empty" role="status"><i class="ph ph-textbox" aria-hidden="true"></i><h2>Enter at least 2 characters to search.</h2><p>Use a project key, task title, description, or label.</p></div>
        @elseif ($projects->isEmpty() && $tasks->isEmpty())
            <div class="projects-empty-state my-work-empty" role="status"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><h2>No results for “{{ $term }}”.</h2><p>Try a different key, title, or label.</p></div>
        @else
            <div class="search-results" aria-live="polite"><p class="planops-eyebrow">Results for “{{ $term }}”</p>@if ($projects->isNotEmpty())<section class="search-result-group" aria-labelledby="search-projects-heading"><h2 id="search-projects-heading">Projects <span>{{ $projects->count() }}</span></h2><x-search.result-list :items="$projects" type="Projects" /></section>@endif @if ($tasks->isNotEmpty())<section class="search-result-group" aria-labelledby="search-tasks-heading"><h2 id="search-tasks-heading">Tasks <span>{{ $tasks->count() }}</span></h2><x-search.result-list :items="$tasks" type="Tasks" /></section>@endif</div>
        @endif
    </section></div>
</x-app-layout>
