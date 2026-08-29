<?php

namespace App\Http\Controllers;

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Queries\MyWorkQuery;
use App\Http\Requests\MyWorkFiltersRequest;
use Illuminate\View\View;

final class MyWorkController extends Controller
{
    public function index(MyWorkFiltersRequest $request, MyWorkQuery $tasks): View
    {
        $owner = $request->user();
        $filters = $request->filters();

        return view('pages.my-work.index', [
            'tasks' => $tasks->paginate($owner, $filters),
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
