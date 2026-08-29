<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('project')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
            'ordered_task_ids' => ['required', 'array', 'min:1'],
            'ordered_task_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
