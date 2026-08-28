<?php

namespace App\Http\Requests;

use App\Domain\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class ChangeTaskDueDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            && ($this->user()?->can('changeDueDate', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'due_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
