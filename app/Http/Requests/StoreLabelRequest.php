<?php

namespace App\Http\Requests;

use App\Domain\Labels\Models\Label;
use App\Domain\Labels\Rules\NormalizedLabelName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $name = $this->input('name');

                if (! is_string($name) || $validator->errors()->has('name')) {
                    return;
                }

                $normalizedName = (new NormalizedLabelName)->normalize($name);
                $exists = Label::query()
                    ->where('user_id', $this->user()?->getKey())
                    ->where('normalized_name', $normalizedName)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'The name has already been taken.');
                }
            },
        ];
    }
}
