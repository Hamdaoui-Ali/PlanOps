<?php

namespace App\Http\Controllers;

use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Queries\ProjectTaskListQuery;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Domain\Tasks\Models\Task;
use App\Http\Requests\ProjectTaskListFiltersRequest;
use Illuminate\View\View;

final class ProjectTaskListController extends Controller
{
    public function index(ProjectTaskListFiltersRequest $request, Project $project, ProjectTaskListQuery $tasks, TaskKeyQuery $keys): View
    {
        $owner = $request->user();
        $filters = $request->filters();

        return view('pages.projects.tasks', [
            'project' => $project,
            'tasks' => $tasks->paginate($owner, $project, $filters),
            'keys' => $keys,
            'filters' => $filters,
            'hasAnyTasks' => Task::query()->ownedBy($owner)->where('project_id', $project->getKey())->exists(),
            'labels' => Label::query()->ownedBy($owner)->orderBy('normalized_name')->get(['id', 'name']),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
        ]);
    }
}
