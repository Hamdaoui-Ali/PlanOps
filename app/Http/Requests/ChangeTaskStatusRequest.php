<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('task') instanceof Task
            && ($this->user()?->can('update', $this->route('task')) ?? false);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Choose a task status.',
            'status.in' => 'Choose a valid task status.',
        ];
    }
}
