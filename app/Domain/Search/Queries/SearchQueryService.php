<?php

namespace App\Domain\Search\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class SearchQueryService
{
    /** @return array{tasks: Collection<int, Task>, projects: Collection<int, Project>, term: string} */
    public function search(User $user, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return ['tasks' => new Collection, 'projects' => new Collection, 'term' => $term];
        }

        $needle = '%'.mb_strtolower($term).'%';
        $tasks = Task::query()
            ->ownedBy($user)
            ->with(['project', 'labels'])
            ->where(function (Builder $query) use ($needle): void {
                $query->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw("LOWER((SELECT key FROM projects WHERE projects.id = tasks.project_id) || '-' || CAST(tasks.number AS TEXT)) LIKE ?", [$needle])
                    ->orWhereHas('project', fn (Builder $projects): Builder => $projects
                        ->whereRaw('LOWER(name) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(key) LIKE ?', [$needle]))
                    ->orWhereHas('labels', fn (Builder $labels): Builder => $labels
                        ->ownedBy($user)
                        ->whereRaw('LOWER(name) LIKE ?', [$needle]));
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $projects = Project::query()
            ->ownedBy($user)
            ->where(function (Builder $query) use ($needle): void {
                $query->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(key) LIKE ?', [$needle]);
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get();

        return ['tasks' => $tasks, 'projects' => $projects, 'term' => $term];
    }
}
