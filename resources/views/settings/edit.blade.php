<x-app-layout>
    @php
        $weekStartDay = $preference?->week_start_day?->value ?? 'MONDAY';
        $theme = $preference?->theme?->value ?? 'SYSTEM';
        $density = $preference?->density?->value ?? 'COMFORTABLE';
    @endphp

    <main class="settings-page" aria-labelledby="settings-heading">
        <header class="settings-header">
            <div>
                <p class="planops-eyebrow">Account / preferences</p>
                <h1 id="settings-heading">Settings</h1>
                <p>Set how PlanOps displays your workspace and interprets calendar dates.</p>
            </div>
        </header>

        @if (session('status'))
            <div class="planops-flash" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('settings.preferences.update') }}" class="settings-form">
            @csrf
            @method('PATCH')

            <section class="settings-section" aria-labelledby="settings-calendar-heading">
                <div class="settings-section-heading">
                    <p class="planops-eyebrow">Calendar</p>
                    <h2 id="settings-calendar-heading">Workspace dates</h2>
                    <p>Choose the timezone and week boundary used across your views.</p>
                </div>

                <div class="settings-grid">
                    <div class="settings-field">
                        <x-input-label for="timezone" :value="__('Timezone')" />
                        <select id="timezone" name="timezone" aria-describedby="timezone-help">
                            @foreach ($timezones as $timezone)
                                <option value="{{ $timezone }}" @selected($preference->timezone === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </select>
                        <p id="timezone-help" class="settings-help">Timezone controls Today, Week, Month, and Year boundaries.</p>
                        <x-input-error :messages="$errors->get('timezone')" />
                    </div>

                    <div class="settings-field">
                        <x-input-label for="week_start_day" :value="__('Week starts on')" />
                        <select id="week_start_day" name="week_start_day" aria-describedby="week-start-help">
                            <option value="MONDAY" @selected($weekStartDay === 'MONDAY')>Monday</option>
                            <option value="SUNDAY" @selected($weekStartDay === 'SUNDAY')>Sunday</option>
                        </select>
                        <p id="week-start-help" class="settings-help">This sets the first column in weekly planning views.</p>
                    </div>
                </div>
            </section>

            <section class="settings-section" aria-labelledby="settings-display-heading">
                <div class="settings-section-heading">
                    <p class="planops-eyebrow">Display</p>
                    <h2 id="settings-display-heading">Your workspace feel</h2>
                    <p>Keep the interface comfortable for the way you plan and review work.</p>
                </div>

                <div class="settings-grid">
                    <div class="settings-field">
                        <x-input-label for="theme" :value="__('Theme')" />
                        <select id="theme" name="theme" aria-describedby="theme-help">
                            <option value="SYSTEM" @selected($theme === 'SYSTEM')>System</option>
                            <option value="LIGHT" @selected($theme === 'LIGHT')>Light</option>
                            <option value="DARK" @selected($theme === 'DARK')>Dark</option>
                        </select>
                        <p id="theme-help" class="settings-help">System follows your device preference.</p>
                    </div>

                    <div class="settings-field">
                        <x-input-label for="density" :value="__('Density')" />
                        <select id="density" name="density" aria-describedby="density-help">
                            <option value="COMFORTABLE" @selected($density === 'COMFORTABLE')>Comfortable</option>
                            <option value="COMPACT" @selected($density === 'COMPACT')>Compact</option>
                        </select>
                        <p id="density-help" class="settings-help">Comfortable adds breathing room; Compact shows more at once.</p>
                    </div>
                </div>
            </section>

            <div class="settings-actions">
                <button type="submit" class="planops-button planops-button-primary">{{ __('Save preferences') }}</button>
            </div>
        </form>
    </main>
</x-app-layout>
