<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['period', 'from', 'until']))
            ->map(fn (mixed $value): mixed => $value === '' ? null : $value)
            ->all());
    }

    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', Rule::in(['today', 'week', 'month', 'year', 'custom'])],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'until' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function period(): string
    {
        return $this->validated('period') ?: 'today';
    }
}
