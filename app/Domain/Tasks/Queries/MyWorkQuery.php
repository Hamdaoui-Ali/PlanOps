<?php

namespace App\Domain\Tasks\Queries;

use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class MyWorkQuery
{
    private const DEFAULT_STATUSES = [
        TaskStatus::IN_PROGRESS->value,
        TaskStatus::IN_REVIEW->value,
        TaskStatus::BLOCKED->value,
        TaskStatus::NOT_STARTED->value,
    ];

    public function paginate(User $owner, array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $perPage = min(50, max(1, $perPage));
        $timezone = $owner->preference?->timezone ?? 'Africa/Casablanca';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $query = Task::query()
            ->ownedBy($owner)
            ->with(['project', 'labels'])
            ->withCount([
                'children',
                'children as eligible_children_count' => fn (Builder $children): Builder => $children
                    ->where('status', '!=', TaskStatus::CANCELLED->value),
                'children as completed_children_count' => fn (Builder $children): Builder => $children
                    ->where('status', TaskStatus::DONE->value),
            ])
            ->when(array_key_exists('status', $filters), fn (Builder $tasks): Builder => $tasks->where('status', $filters['status']))
            ->when(! array_key_exists('status', $filters), fn (Builder $tasks): Builder => $tasks->whereIn('status', self::DEFAULT_STATUSES))
            ->when($filters['project'] ?? null, fn (Builder $tasks, int|string $project): Builder => $tasks->where('project_id', $project))
            ->when($filters['priority'] ?? null, fn (Builder $tasks, string $priority): Builder => $tasks->where('priority', $priority))
            ->when($filters['label'] ?? null, fn (Builder $tasks, int|string $label): Builder => $tasks->whereHas('labels', fn (Builder $labels): Builder => $labels->whereKey($label)->ownedBy($owner)))
            ->when($filters['created_from'] ?? null, fn (Builder $tasks, string $date): Builder => $tasks->whereDate('created_at', '>=', $date))
            ->when($filters['created_until'] ?? null, fn (Builder $tasks, string $date): Builder => $tasks->whereDate('created_at', '<=', $date))
            ->when($filters['updated_from'] ?? null, fn (Builder $tasks, string $date): Builder => $tasks->whereDate('updated_at', '>=', $date))
            ->when($filters['updated_until'] ?? null, fn (Builder $tasks, string $date): Builder => $tasks->whereDate('updated_at', '<=', $date));

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
            'task_key' => $query->orderBy('project_id')->orderBy('number')->orderBy('id'),
            'project' => $query->orderBy('project_id')->orderBy('title')->orderBy('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };
    }
}
