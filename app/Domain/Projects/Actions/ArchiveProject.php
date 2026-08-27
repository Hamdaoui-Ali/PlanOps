<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ArchiveProject
{
    public function handle(User $user, Project $project): Project
    {
        Gate::forUser($user)->authorize('archive', $project);

        if ($project->archived_at === null) {
            $project->forceFill(['archived_at' => now()])->save();
        }

        return $project->refresh();
    }
}
