@props([
    'labels',
    'selectedLabelIds',
    'attachAction',
    'detachAction',
    'createAction' => null,
])

@php
    $selectedIds = collect($selectedLabelIds)->map(fn ($labelId) => (string) $labelId)->all();
    $attachedLabels = collect($labels)->filter(fn ($label) => in_array((string) $label->id, $selectedIds, true));
@endphp

<section class="label-picker" aria-labelledby="label-picker-heading">
    <div class="label-picker-heading">
        <i class="ph ph-tag" aria-hidden="true"></i>
        <h2 id="label-picker-heading">Labels</h2>
    </div>

    <form method="POST" action="{{ $attachAction }}" class="label-picker-form label-picker-add-form">
        @csrf

        <div class="label-picker-field">
            <label for="task-label-id">Add label</label>
            <select id="task-label-id" name="label_id">
                <option value="">Choose a label</option>
                @foreach ($labels as $label)
                    @unless (in_array((string) $label->id, $selectedIds, true))
                        <option value="{{ $label->id }}" @selected((string) old('label_id') === (string) $label->id)>{{ $label->name }}</option>
                    @endunless
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('label_id')" />
        </div>

        <button type="submit" class="planops-button planops-button-secondary">
            <i class="ph ph-plus" aria-hidden="true"></i>
            <span>Attach label</span>
        </button>
    </form>

    <div class="label-picker-attached" aria-labelledby="attached-labels-heading">
        <h3 id="attached-labels-heading">Attached labels</h3>

        <ul class="label-picker-list">
            @forelse ($attachedLabels as $label)
                <li class="label-picker-item">
                    <span class="label-picker-name">{{ $label->name }}</span>

                    <form method="POST" action="{{ $detachAction }}" class="label-picker-remove-form">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="label_id" value="{{ $label->id }}">
                        <button type="submit" class="label-picker-remove">
                            <i class="ph ph-x" aria-hidden="true"></i>
                            <span>Remove {{ $label->name }}</span>
                        </button>
                    </form>
                </li>
            @empty
                <li class="label-picker-empty">No labels attached.</li>
            @endforelse
        </ul>
    </div>

    @if ($createAction !== null)
        <form method="POST" action="{{ $createAction }}" class="label-picker-form label-picker-create-form">
            @csrf

            <div class="label-picker-field">
                <label for="task-new-label">Create label</label>
                <input id="task-new-label" name="name" type="text" value="{{ old('name') }}" maxlength="80" autocomplete="off">
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <button type="submit" class="planops-button planops-button-secondary">
                <i class="ph ph-tag-simple" aria-hidden="true"></i>
                <span>Create label</span>
            </button>
        </form>
    @endif
</section>
