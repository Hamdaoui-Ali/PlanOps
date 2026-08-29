<?php

namespace App\Http\Controllers;

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Queries\MyWorkQuery;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Http\Requests\MyWorkFiltersRequest;
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
            'hasAnyTasks' => Task::query()->ownedBy($owner)->exists(),
            'filters' => $filters,
            'projects' => Project::query()->ownedBy($owner)->orderBy('name')->get(['id', 'name', 'key']),
            'labels' => Label::query()->ownedBy($owner)->orderBy('normalized_name')->get(['id', 'name']),
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
