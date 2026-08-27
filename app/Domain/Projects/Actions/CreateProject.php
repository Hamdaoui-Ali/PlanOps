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
            'color' => $this->nullableTrimmed($attributes['color'] ?? null),
            'icon' => $this->nullableTrimmed($attributes['icon'] ?? null),
            'start_on' => $this->nullableValue($attributes['start_on'] ?? null),
            'target_on' => $this->nullableValue($attributes['target_on'] ?? null),
            'status' => $this->statusValue($attributes['status'] ?? ProjectStatus::PLANNED),
        ];

        Validator::make($values, [
            'name' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('projects', 'key')->where('user_id', $user->getKey())],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:64'],
            'start_on' => ['nullable', 'date'],
            'target_on' => ['nullable', 'date', 'after_or_equal:start_on'],
            'status' => ['required', 'string', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
        ])->validate();

        $values['status'] = ProjectStatus::from($values['status']);

        return Project::query()->create([
            ...$values,
            'user_id' => $user->getKey(),
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

    private function statusValue(mixed $status): mixed
    {
        return $status instanceof ProjectStatus ? $status->value : $this->trimmed($status);
    }
}
