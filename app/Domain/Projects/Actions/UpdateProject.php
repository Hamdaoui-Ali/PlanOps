<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateProject
{
    public function handle(User $user, Project $project, array $attributes): Project
    {
        Gate::forUser($user)->authorize('update', $project);

        $values = [
            'name' => $this->trimmed($attributes['name'] ?? $project->name),
            'key' => $this->uppercased($attributes['key'] ?? $project->key),
            'description' => array_key_exists('description', $attributes)
                ? $this->nullableTrimmed($attributes['description'])
                : $project->description,
            'start_on' => $this->nullableValue($attributes['start_on'] ?? $project->start_on),
            'target_on' => $this->nullableValue($attributes['target_on'] ?? $project->target_on),
        ];

        $validator = Validator::make($values, [
            'name' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('projects', 'key')->where('user_id', $user->getKey())->ignore($project->getKey())],
            'description' => ['nullable', 'string'],
            'start_on' => ['nullable', 'date'],
            'target_on' => ['nullable', 'date', 'after_or_equal:start_on'],
        ]);

        $validator->after(function ($validator) use ($project, $values): void {
            if ($values['key'] !== $project->key && $project->hasTasksEver()) {
                $validator->errors()->add('key', 'A project key cannot change after the project has contained a task.');
            }
        });
        $validator->validate();

        $project->fill($values)->save();

        return $project->refresh();
    }

    private function trimmed(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function uppercased(mixed $value): mixed
    {
        return is_string($value) ? strtoupper(trim($value)) : $value;
    }

    private function nullableTrimmed(mixed $value): mixed
    {
        $value = $this->trimmed($value);

        return $value === '' ? null : $value;
    }

    private function nullableValue(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
