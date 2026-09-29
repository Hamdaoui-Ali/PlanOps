<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Collaboration\Enums\ProjectEventType;
use App\Domain\Collaboration\Models\ProjectEvent;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class ProjectActivityFeedQuery
{
    public function for(User|int $viewer, Project|int $project, int $limit = 10): Collection
    {
        $viewerId = $viewer instanceof User ? $viewer->getKey() : $viewer;
        $projectId = $project instanceof Project ? $project->getKey() : $project;

        return ProjectEvent::query()
            ->whereIn('project_id', Project::query()->accessibleBy($viewerId)->select('id'))
            ->where('project_id', $projectId)
            ->where('event_type', ProjectEventType::MEMBER_ROLE_CHANGED->value)
            ->with([
                'actor:id,name',
                'subject:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(min(50, max(1, $limit)))
            ->get();
    }
}
