<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['q' => is_string($this->input('q')) ? trim($this->input('q')) : $this->input('q')]);
    }

    public function rules(): array
    {
        return ['q' => ['nullable', 'string', 'max:100']];
    }

    public function term(): string
    {
        return (string) ($this->validated('q') ?? '');
    }
}
