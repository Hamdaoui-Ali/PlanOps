<?php

namespace App\Http\Requests;

use App\Domain\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) ?? false;
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'name' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('projects', 'key')->where('user_id', $this->user()?->getKey())->ignore($project)],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:64'],
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
            'key.unique' => 'You already have another project with this key.',
            'target_on.after_or_equal' => 'The target date must be on or after the start date.',
        ];
    }
}
