<?php

namespace App\Domain\Tasks\Queries;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ProjectTaskListQuery
{
    public function paginate(User $owner, Project $project, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $project = Project::query()->ownedBy($owner)->whereKey($project->getKey())->firstOrFail();
        $perPage = min(50, max(1, $perPage));
        $timezone = $owner->preference?->timezone ?? 'Africa/Casablanca';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $query = Task::query()
            ->ownedBy($owner)
            ->where('project_id', $project->getKey())
            ->with(['project', 'parent', 'labels'])
            ->withCount([
                'children',
                'children as eligible_children_count' => fn (Builder $children): Builder => $children
                    ->where('status', '!=', TaskStatus::CANCELLED->value),
                'children as completed_children_count' => fn (Builder $children): Builder => $children
                    ->where('status', TaskStatus::DONE->value),
            ])
            ->when($filters['status'] ?? null, fn (Builder $tasks, string $status): Builder => $tasks->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $tasks, string $priority): Builder => $tasks->where('priority', $priority))
            ->when($filters['label'] ?? null, fn (Builder $tasks, int|string $label): Builder => $tasks->whereHas('labels', fn (Builder $labels): Builder => $labels->whereKey($label)->ownedBy($owner)));

        $this->applyDueFilter($query, $filters['due'] ?? null, $today);
        $this->applySort($query, $filters['sort'] ?? 'updated');

        return $query->paginate($perPage)->withQueryString();
    }

    private function applyDueFilter(Builder $query, ?string $due, CarbonImmutable $today): void
    {
        match ($due) {
            'overdue' => $query->whereDate('due_on', '<', $today->toDateString()),
            'today' => $query->whereDate('due_on', $today->toDateString()),
            'this_week' => $query->whereBetween('due_on', [$today->startOfWeek()->toDateString(), $today->endOfWeek()->toDateString()]),
            'no_due_date' => $query->whereNull('due_on'),
            default => null,
        };
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'created' => $query->orderByDesc('created_at')->orderByDesc('id'),
            'priority' => $query->orderByRaw("CASE priority WHEN 'URGENT' THEN 1 WHEN 'HIGH' THEN 2 WHEN 'MEDIUM' THEN 3 WHEN 'LOW' THEN 4 ELSE 5 END")->orderBy('id'),
            'due' => $query->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderBy('id'),
            'task_key' => $query->orderBy('number')->orderBy('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }
}
