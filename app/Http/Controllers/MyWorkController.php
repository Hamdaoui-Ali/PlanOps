<?php

namespace App\Http\Controllers;

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\MyWorkQuery;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Http\Requests\MyWorkFiltersRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

final class MyWorkController extends Controller
{
    public function index(MyWorkFiltersRequest $request, MyWorkQuery $tasks, TaskKeyQuery $keys): View
    {
        $owner = $request->user();
        $filters = $request->filters();

        return view('pages.my-work.index', [
            'tasks' => $tasks->paginate($owner, $filters),
            'keys' => $keys,
            'hasAnyAssignedTasks' => Task::query()
                ->accessibleBy($owner)
                ->where(function (Builder $tasks) use ($owner): void {
                    $tasks->where('assignee_id', $owner->getKey())
                        ->orWhere(function (Builder $legacy) use ($owner): void {
                            $legacy->whereNull('assignee_id')->where('user_id', $owner->getKey())
                                ->whereHas('project', fn (Builder $projects): Builder => $projects->whereDoesntHave('memberships'));
                        });
                })
                ->exists(),
            'filters' => $filters,
            'projects' => Project::query()->accessibleBy($owner)->orderBy('name')->get(['id', 'name', 'key']),
            'labels' => Label::query()->accessibleBy($owner)->orderBy('normalized_name')->get(['id', 'name']),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'focusStatuses' => [
                TaskStatus::IN_PROGRESS,
                TaskStatus::IN_REVIEW,
                TaskStatus::BLOCKED,
                TaskStatus::NOT_STARTED,
            ],
        ]);
    }
}
