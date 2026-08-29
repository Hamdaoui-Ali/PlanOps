<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskDetailQuery;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Domain\Activity\Queries\TaskActivityFeedQuery;
use App\Http\Requests\ChangeTaskDueDateRequest;
use App\Http\Requests\ChangeTaskPriorityRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\ChangeTaskStatusRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function show(Request $request, Task $task, TaskDetailQuery $details, TaskActivityFeedQuery $activity, TaskKeyQuery $keys): View
    {
        $task = $details->for($request->user(), $task);

        return view('pages.tasks.show', [
            'task' => $task,
            'displayKey' => $keys->displayKey($task),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'activities' => $activity->forTask($request->user(), $task),
        ]);
    }

    public function changeStatus(ChangeTaskStatusRequest $request, Task $task, ChangeTaskStatus $changeStatus): RedirectResponse
    {
        $changeStatus->handle($request->user(), $task, $request->validated('status'));

        return to_route('projects.show', $task->project_id)->with('status', 'Task status updated.');
    }

    public function create(Project $project, TaskKeyQuery $keys): View
    {
        $parentOptions = $project->tasks()
            ->ownedBy($project->user_id)
            ->whereNull('parent_task_id')
            ->orderBy('number')
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->getKey(),
                'display_key' => $keys->displayKey($task),
                'title' => $task->title,
            ]);

        return view('pages.tasks.create', [
            'project' => $project,
            'parentOptions' => $parentOptions,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
        ]);
    }

    public function store(StoreTaskRequest $request, Project $project, CreateTask $create, TaskKeyQuery $keys): RedirectResponse
    {
        $task = $create->handle($request->user(), $project, $request->validated());

        return to_route('projects.show', $project)
            ->with('status', $keys->displayKey($task).' created.');
    }
}
