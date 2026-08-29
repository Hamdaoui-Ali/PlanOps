<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('update', $task) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? trim($this->input('title')) : $this->input('title'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'priority' => ['required', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Enter a task title.',
            'status.required' => 'Choose a task status.',
            'status.in' => 'Choose a valid task status.',
            'priority.required' => 'Choose a task priority.',
            'priority.in' => 'Choose a valid task priority.',
            'due_on.date_format' => 'Enter a valid due date.',
        ];
    }
}
