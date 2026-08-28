<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTaskPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('changePriority', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'priority' => ['required', 'string', Rule::in(array_column(TaskPriority::cases(), 'value'))],
        ];
    }
}
