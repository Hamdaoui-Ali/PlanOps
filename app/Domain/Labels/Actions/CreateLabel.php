<?php

namespace App\Domain\Labels\Actions;

use App\Domain\Labels\Models\Label;
use App\Domain\Labels\Rules\NormalizedLabelName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateLabel
{
    public function handle(User $user, array $attributes): Label
    {
        $names = new NormalizedLabelName;
        $values = [
            'name' => is_string($attributes['name'] ?? null) ? $names->displayName($attributes['name']) : ($attributes['name'] ?? null),
            'color' => $attributes['color'] ?? null,
        ];

        if ($values['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'Enter a label name.',
            ]);
        }

        Validator::make($values, [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:32'],
        ])->validate();

        $normalizedName = $names->normalize($values['name']);

        Validator::make(['normalized_name' => $normalizedName], [
            'normalized_name' => [
                'required',
                Rule::unique('labels', 'normalized_name')->where(
                    fn ($query) => $query->where('user_id', $user->getKey()),
                ),
            ],
        ])->validate();

        Gate::forUser($user)->authorize('create', Label::class);

        return Label::query()->create([
            'user_id' => $user->getKey(),
            'name' => $values['name'],
            'normalized_name' => $normalizedName,
            'color' => $values['color'],
        ]);
    }
}
