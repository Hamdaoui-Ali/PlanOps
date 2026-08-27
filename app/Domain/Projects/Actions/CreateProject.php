<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateProject
{
    public function handle(User $user, array $attributes): Project
    {
        Gate::forUser($user)->authorize('create', Project::class);

        $values = [
            'name' => $this->trimmed($attributes['name'] ?? ''),
            'key' => $this->uppercased($attributes['key'] ?? ''),
            'description' => $this->nullableTrimmed($attributes['description'] ?? null),
            'start_on' => $this->nullableValue($attributes['start_on'] ?? null),
            'target_on' => $this->nullableValue($attributes['target_on'] ?? null),
        ];

        Validator::make($values, [
            'name' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('projects', 'key')->where('user_id', $user->getKey())],
            'description' => ['nullable', 'string'],
            'start_on' => ['nullable', 'date'],
            'target_on' => ['nullable', 'date', 'after_or_equal:start_on'],
        ])->validate();

        return Project::query()->create([
            ...$values,
            'user_id' => $user->getKey(),
            'status' => ProjectStatus::PLANNED,
            'next_task_number' => 1,
        ]);
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
