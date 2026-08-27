<?php

namespace App\Domain\Projects\Queries;

use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProjectIndexQuery
{
    public function paginate(User|int $owner, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Project::query()->ownedBy($owner)->withCount([
            'tasks as eligible_task_count' => fn (Builder $tasks): Builder => $this->eligibleTasks($tasks),
            'tasks as completed_task_count' => fn (Builder $tasks): Builder => $this->eligibleTasks($tasks)->where('status', TaskStatus::DONE->value),
        ]);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? 'updated');

        return $query->paginate(min(max($perPage, 1), 50))->withQueryString();
    }

    private function eligibleTasks(Builder $tasks): Builder
    {
        return $tasks->whereNull('parent_task_id')->where('status', '!=', TaskStatus::CANCELLED->value);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $archived = $filters['archived'] ?? 'active';
        if (in_array($archived, [true, 1, '1', 'true', 'archived'], true)) {
            $query->whereNotNull('archived_at');
        } elseif ($archived !== 'all') {
            $query->whereNull('archived_at');
        }

        if (is_string($filters['search'] ?? null) && trim($filters['search']) !== '') {
            $search = '%'.strtolower(trim($filters['search'])).'%';
            $query->where(function (Builder $projects) use ($search): void {
                $projects->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(key) LIKE ?', [$search]);
            });
        }

        $status = $filters['status'] ?? null;
        $status = $status instanceof ProjectStatus ? $status : (is_string($status) ? ProjectStatus::tryFrom($status) : null);
        if ($status instanceof ProjectStatus) {
            $query->where('status', $status->value);
        }

        match ($filters['target_date'] ?? null) {
            'overdue' => $query->whereNotNull('target_on')->whereDate('target_on', '<', today()),
            'no_target' => $query->whereNull('target_on'),
            default => null,
        };
    }

    private function applySort(Builder $query, mixed $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name')->orderBy('id'),
            'progress' => $this->orderByProgress($query),
            'target_on' => $query->orderBy('target_on')->orderBy('id'),
            'created' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }

    private function orderByProgress(Builder $query): Builder
    {
        $eligible = '(SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.parent_task_id IS NULL AND tasks.deleted_at IS NULL AND tasks.status <> ?)';
        $completed = '(SELECT COUNT(*) FROM tasks WHERE tasks.project_id = projects.id AND tasks.parent_task_id IS NULL AND tasks.deleted_at IS NULL AND tasks.status = ?)';

        return $query->orderByRaw(
            "CASE WHEN {$eligible} = 0 THEN 0 ELSE (1.0 * {$completed} / {$eligible}) END DESC",
            [TaskStatus::CANCELLED->value, TaskStatus::DONE->value, TaskStatus::CANCELLED->value],
        )->orderBy('id');
    }
}
