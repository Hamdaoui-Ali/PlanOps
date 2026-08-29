<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProjectTaskListFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only([
            'status', 'priority', 'label', 'due', 'sort',
        ]))->map(fn (mixed $value): mixed => $value === '' ? null : $value)->all());
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'priority' => ['nullable', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'label' => ['nullable', 'integer'],
            'due' => ['nullable', 'string', Rule::in(['overdue', 'today', 'this_week', 'no_due_date'])],
            'sort' => ['nullable', 'string', Rule::in(['updated', 'created', 'priority', 'due', 'task_key'])],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return array_filter($this->validated(), fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
