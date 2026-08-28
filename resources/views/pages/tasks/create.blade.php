<x-app-layout>
    <x-slot name="header">
        <p class="font-semibold text-xl text-gray-800 leading-tight">Task capture</p>
    </x-slot>

    <div class="planops-console">
        <section class="task-capture-page" aria-labelledby="task-capture-heading">
            @if (session('status'))
                <div class="planops-flash" role="status">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <header class="task-capture-header">
                <p class="planops-eyebrow">Project / task capture</p>
                <h1 id="task-capture-heading">Create task</h1>
                <p>Capture the next piece of work for {{ $project->name }}.</p>
            </header>

            @include('components.tasks.quick-create')
        </section>
    </div>
</x-app-layout>
