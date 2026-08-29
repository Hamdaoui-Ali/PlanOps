<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MyWorkFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'project', 'status', 'priority', 'label', 'due', 'sort',
            'created_from', 'created_until', 'updated_from', 'updated_until',
        ]))->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all());
    }

    public function rules(): array
    {
        return [
            'project' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'priority' => ['nullable', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'label' => ['nullable', 'integer'],
            'due' => ['nullable', 'string', Rule::in(['overdue', 'today', 'this_week', 'no_due_date'])],
            'sort' => ['nullable', 'string', Rule::in(['updated', 'created', 'priority', 'due', 'task_key', 'project'])],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_until' => ['nullable', 'date_format:Y-m-d'],
            'updated_from' => ['nullable', 'date_format:Y-m-d'],
            'updated_until' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter($this->validated(), fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
