<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">Create project</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('projects.store') }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf

                <div>
                    <label for="name" class="block font-medium text-sm text-gray-700">Project name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="80" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="key" class="block font-medium text-sm text-gray-700">Project key</label>
                    <input id="key" name="key" type="text" value="{{ old('key') }}" required minlength="2" maxlength="10" pattern="[A-Za-z0-9]{2,10}" autocapitalize="characters" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <p class="mt-1 text-sm text-gray-600">Use 2 to 10 letters or numbers. It will be saved in uppercase.</p>
                    <x-input-error class="mt-2" :messages="$errors->get('key')" />
                </div>

                <div>
                    <label for="description" class="block font-medium text-sm text-gray-700">Description <span class="text-gray-500">(optional)</span></label>
                    <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="start_on" class="block font-medium text-sm text-gray-700">Start date <span class="text-gray-500">(optional)</span></label>
                        <input id="start_on" name="start_on" type="date" value="{{ old('start_on') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <x-input-error class="mt-2" :messages="$errors->get('start_on')" />
                    </div>

                    <div>
                        <label for="target_on" class="block font-medium text-sm text-gray-700">Target date <span class="text-gray-500">(optional)</span></label>
                        <input id="target_on" name="target_on" type="date" value="{{ old('target_on') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <x-input-error class="mt-2" :messages="$errors->get('target_on')" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Create project</button>
                    <a href="{{ route('projects.index') }}" class="text-sm text-gray-700 underline">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
