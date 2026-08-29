<?php

namespace App\Http\Requests;

use App\Domain\Activity\Enums\TaskActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ActivityFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'project_id', 'task_id', 'event_type', 'from', 'until',
        ]))->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all());
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer'],
            'task_id' => ['nullable', 'integer'],
            'event_type' => ['nullable', 'string', Rule::in(array_column(TaskActivityType::cases(), 'value'))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter($this->validated(), fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
