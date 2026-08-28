<x-app-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">Edit project</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">{{ session('status') }}</p>
            @endif

            <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                @csrf
                @method('PATCH')

                <div>
                    <label for="name" class="block font-medium text-sm text-gray-700">Project name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required maxlength="80" autocomplete="off" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <x-input-error class="mt-2" :messages="$errors->get('name')" />
                </div>

                <div>
                    <label for="key" class="block font-medium text-sm text-gray-700">Project key</label>
                    <input id="key" name="key" type="text" value="{{ old('key', $project->key) }}" required minlength="2" maxlength="10" pattern="[A-Za-z0-9]{2,10}" autocapitalize="characters" @readonly($project->hasTasksEver()) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @if ($project->hasTasksEver())
                        <p class="mt-1 text-sm text-gray-600">This key is read-only because this project has task history.</p>
                    @else
                        <p class="mt-1 text-sm text-gray-600">Use 2 to 10 letters or numbers. It will be saved in uppercase.</p>
                    @endif
                    <x-input-error class="mt-2" :messages="$errors->get('key')" />
                </div>

                <div>
                    <label for="description" class="block font-medium text-sm text-gray-700">Description <span class="text-gray-500">(optional)</span></label>
                    <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $project->description) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('description')" />
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div>
                        <label for="start_on" class="block font-medium text-sm text-gray-700">Start date <span class="text-gray-500">(optional)</span></label>
                        <input id="start_on" name="start_on" type="date" value="{{ old('start_on', $project->start_on?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <x-input-error class="mt-2" :messages="$errors->get('start_on')" />
                    </div>

                    <div>
                        <label for="target_on" class="block font-medium text-sm text-gray-700">Target date <span class="text-gray-500">(optional)</span></label>
                        <input id="target_on" name="target_on" type="date" value="{{ old('target_on', $project->target_on?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <x-input-error class="mt-2" :messages="$errors->get('target_on')" />
                    </div>
                </div>

                <div class="project-edit-actions flex items-center gap-4">
                    <button type="submit" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Save project details</button>
                    <a href="{{ route('projects.tasks.create', $project) }}" class="task-create-link">Create task</a>
                    <a href="{{ route('projects.index') }}" class="text-sm text-gray-700 underline">Back to projects</a>
                </div>
            </form>

            <section aria-labelledby="project-status-heading" class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h2 id="project-status-heading" class="font-semibold text-lg text-gray-900">Project status</h2>
                <form method="POST" action="{{ route('projects.status', $project) }}" class="mt-4 flex flex-wrap items-end gap-4">
                    @csrf
                    <div>
                        <label for="status" class="block font-medium text-sm text-gray-700">Status</label>
                        <select id="status" name="status" class="mt-1 block rounded-md border-gray-300 shadow-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $project->status->value) === $status->value)>{{ str($status->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('status')" />
                    </div>
                    <button type="submit" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Update status</button>
                </form>
            </section>

            <section aria-labelledby="project-archive-heading" class="bg-white p-6 shadow-sm sm:rounded-lg">
                <h2 id="project-archive-heading" class="font-semibold text-lg text-gray-900">Archive</h2>
                @if ($project->archived_at)
                    <p class="mt-2 text-sm text-gray-600">This project is archived. Restoring it makes it available again without changing its tasks or activity.</p>
                    <form method="POST" action="{{ route('projects.restore', $project) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white">Restore project</button>
                    </form>
                @else
                    <p class="mt-2 text-sm text-gray-600">Archiving hides this project from the active list. It does not delete the project, its tasks, or activity.</p>
                    <form method="POST" action="{{ route('projects.archive', $project) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white">Archive project</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
