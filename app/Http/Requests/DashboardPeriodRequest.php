<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DashboardPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['period', 'from', 'until']))->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all());
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['today', 'week', 'month', 'year', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'until' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function selection(): array
    {
        return array_filter([
            'period' => $this->validated('period') ?: 'today',
            'from' => $this->validated('from'),
            'until' => $this->validated('until'),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
