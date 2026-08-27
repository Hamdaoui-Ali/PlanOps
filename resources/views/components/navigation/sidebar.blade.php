<div class="planops-sidebar">
    <div class="planops-nav-section">
        <p class="planops-nav-label">Workspace</p>
        <a href="{{ route('dashboard') }}" @class(['planops-nav-link', 'is-active' => request()->routeIs('dashboard')])>
            <i class="ph ph-grid-four" aria-hidden="true"></i>
            <span>{{ __('Dashboard') }}</span>
        </a>
        <a href="{{ url('/my-work') }}" @class(['planops-nav-link', 'is-active' => request()->is('my-work')])>
            <i class="ph ph-clipboard-text" aria-hidden="true"></i>
            <span>{{ __('My Work') }}</span>
        </a>
        <a href="{{ route('projects.index') }}" @class(['planops-nav-link', 'is-active' => request()->routeIs('projects.*')])>
            <i class="ph ph-folder-open" aria-hidden="true"></i>
            <span>{{ __('Projects') }}</span>
        </a>
        <a href="{{ url('/analytics') }}" @class(['planops-nav-link', 'is-active' => request()->is('analytics')])>
            <i class="ph ph-chart-bar" aria-hidden="true"></i>
            <span>{{ __('Analytics') }}</span>
        </a>
        <a href="{{ url('/activity') }}" @class(['planops-nav-link', 'is-active' => request()->is('activity')])>
            <i class="ph ph-waveform" aria-hidden="true"></i>
            <span>{{ __('Activity') }}</span>
        </a>
    </div>

    <div class="planops-nav-section planops-nav-section-secondary">
        <p class="planops-nav-label">Account</p>
        <a href="{{ route('settings.edit') }}" @class(['planops-nav-link', 'is-active' => request()->routeIs('settings.*')])>
            <i class="ph ph-gear" aria-hidden="true"></i>
            <span>{{ __('Settings') }}</span>
        </a>
    </div>
</div>
