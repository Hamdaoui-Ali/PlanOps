<?php

namespace App\Http\Requests;

use App\Domain\Projects\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeProjectStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('project')) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Choose a project status.',
            'status.in' => 'Choose a valid project status.',
        ];
    }
}
