<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChangeProjectStatus
{
    public function handle(User $user, Project $project, ProjectStatus|string $status): Project
    {
        Gate::forUser($user)->authorize('changeStatus', $project);

        $value = $status instanceof ProjectStatus ? $status->value : $status;
        Validator::make(['status' => $value], [
            'status' => ['required', 'string', Rule::in(array_column(ProjectStatus::cases(), 'value'))],
        ])->validate();

        $project->forceFill(['status' => ProjectStatus::from($value)])->save();

        return $project->refresh();
    }
}
