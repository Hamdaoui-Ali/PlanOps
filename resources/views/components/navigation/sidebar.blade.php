<aside class="planops-sidebar" aria-label="Primary navigation">
    <nav class="space-y-1">
        <a href="{{ route('dashboard') }}" class="block rounded px-3 py-2 text-sm font-medium">{{ __('Dashboard') }}</a>
        <a href="{{ url('/my-work') }}" class="block rounded px-3 py-2 text-sm font-medium">{{ __('My Work') }}</a>
        <a href="{{ url('/projects') }}" class="block rounded px-3 py-2 text-sm font-medium">{{ __('Projects') }}</a>
        <a href="{{ url('/analytics') }}" class="block rounded px-3 py-2 text-sm font-medium">{{ __('Analytics') }}</a>
        <a href="{{ url('/activity') }}" class="block rounded px-3 py-2 text-sm font-medium">{{ __('Activity') }}</a>
        <a href="{{ route('settings.edit') }}" class="block rounded px-3 py-2 text-sm font-medium">{{ __('Settings') }}</a>
    </nav>
</aside>
