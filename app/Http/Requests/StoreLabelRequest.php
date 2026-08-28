<?php

namespace App\Http\Requests;

use App\Domain\Labels\Models\Label;
use App\Domain\Labels\Rules\NormalizedLabelName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Label::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $names = new NormalizedLabelName;
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name) ? $names->displayName($name) : $name,
            'normalized_name' => is_string($name) ? $names->normalize($name) : $name,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:32'],
            'normalized_name' => [
                'required',
                Rule::unique('labels', 'normalized_name')->where(
                    fn ($query) => $query->where('user_id', $this->user()?->getKey()),
                ),
            ],
        ];
    }
}
