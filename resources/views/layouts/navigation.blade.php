<div class="planops-navigation">
    <header class="planops-mobilebar">
        <a href="{{ route('dashboard') }}" class="planops-brand planops-brand-mobile">PLANOPS</a>
        <button
            type="button"
            class="planops-menu-button"
            aria-label="Open navigation menu"
            aria-expanded="false"
            aria-controls="primary-navigation"
            :aria-label="mobileOpen ? 'Close navigation menu' : 'Open navigation menu'"
            @click="mobileOpen = ! mobileOpen; $event.currentTarget.setAttribute('aria-expanded', mobileOpen ? 'true' : 'false')"
        >
            <i class="ph ph-list" aria-hidden="true"></i>
            <span>Menu</span>
        </button>
    </header>

    <nav id="primary-navigation" aria-label="Primary navigation" class="planops-rail" :class="{ 'is-open': mobileOpen }">
        <a href="{{ route('dashboard') }}" class="planops-brand">PLANOPS</a>

        <form method="GET" action="{{ route('search') }}" class="nav-search" role="search">
            <label class="sr-only" for="nav-search-query">Search projects and tasks</label>
            <div><i class="ph ph-magnifying-glass" aria-hidden="true"></i><input id="nav-search-query" name="q" type="search" placeholder="Search…" aria-keyshortcuts="/" autocomplete="off"></div>
        </form>

        @include('components.navigation.sidebar')

        <div class="planops-account">
            <div class="planops-account-copy">
                <span class="planops-account-name">{{ Auth::user()->name }}</span>
                <span class="planops-account-email">{{ Auth::user()->email }}</span>
            </div>
            <a href="{{ route('profile.edit') }}" class="planops-account-link">
                <i class="ph ph-user-circle" aria-hidden="true"></i>
                <span>Profile</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="planops-account-link">
                    <i class="ph ph-sign-out" aria-hidden="true"></i>
                    <span>Log out</span>
                </button>
            </form>
        </div>

        <button
            type="button"
            class="planops-collapse-button"
            aria-label="Collapse navigation rail"
            :aria-label="railCollapsed ? 'Expand navigation rail' : 'Collapse navigation rail'"
            @click="railCollapsed = ! railCollapsed"
        >
            <i class="ph ph-caret-left" aria-hidden="true"></i>
            <span class="planops-collapse-label" x-text="railCollapsed ? 'Expand rail' : 'Collapse rail'">Collapse rail</span>
        </button>
    </nav>
</div>
