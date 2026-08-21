<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Settings') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="mb-4 text-sm text-green-700" role="status">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('settings.preferences.update') }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="timezone" :value="__('Timezone')" />
                    <select id="timezone" name="timezone" class="mt-1 block w-full border-gray-300 rounded-md">
                        @foreach ($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected($preference->timezone === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="week_start_day" :value="__('Week starts on')" />
                    <select id="week_start_day" name="week_start_day" class="mt-1 block w-full border-gray-300 rounded-md">
                        @foreach (['MONDAY', 'SUNDAY'] as $day)<option value="{{ $day }}" @selected($preference->week_start_day === $day)>{{ $day }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="theme" :value="__('Theme')" />
                    <select id="theme" name="theme" class="mt-1 block w-full border-gray-300 rounded-md">
                        @foreach (['SYSTEM', 'LIGHT', 'DARK'] as $theme)<option value="{{ $theme }}" @selected($preference->theme === $theme)>{{ $theme }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="density" :value="__('Density')" />
                    <select id="density" name="density" class="mt-1 block w-full border-gray-300 rounded-md">
                        @foreach (['COMFORTABLE', 'COMPACT'] as $density)<option value="{{ $density }}" @selected($preference->density === $density)>{{ $density }}</option>@endforeach
                    </select>
                </div>
                <x-primary-button>{{ __('Save preferences') }}</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
