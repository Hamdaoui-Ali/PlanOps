<?php

namespace App\Http\Requests;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'key' => is_string($this->input('key')) ? strtoupper(trim($this->input('key'))) : $this->input('key'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('projects', 'key')->where('user_id', $this->user()?->getKey())],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
            'start_on' => ['nullable', 'date'],
            'target_on' => ['nullable', 'date', 'after_or_equal:start_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter a project name.',
            'key.required' => 'Enter a project key.',
            'key.regex' => 'Project keys use 2 to 10 uppercase letters or numbers only.',
            'key.unique' => 'You already have a project with this key.',
            'status.in' => 'Choose a valid project status.',
            'target_on.after_or_equal' => 'The target date must be on or after the start date.',
        ];
    }
}
