<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Models\Project;
use App\Domain\Activity\Queries\TaskActivityFeedQuery;
use App\Domain\Tasks\Actions\ChangeTaskDueDate;
use App\Domain\Tasks\Actions\ChangeTaskPriority;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Actions\DeleteTask;
use App\Domain\Tasks\Actions\UpdateTask;
use App\Domain\Tasks\Actions\UpdateTaskDetails;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskDetailQuery;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Http\Requests\ChangeTaskDueDateRequest;
use App\Http\Requests\ChangeTaskPriorityRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\UpdateTaskDetailsRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\ChangeTaskStatusRequest;
use Illuminate\Http\Request;
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

        return to_route('tasks.show', $task)->with('status', 'Task status updated.');
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $update): RedirectResponse
    {
        $update->handle($request->user(), $task, $request->validated());

        return to_route('tasks.show', $task)->with('status', 'Task details updated.');
    }

    public function updateDetails(UpdateTaskDetailsRequest $request, Task $task, UpdateTaskDetails $update): RedirectResponse
    {
        $update->handle($request->user(), $task, $request->validated());

        return to_route('tasks.show', $task)->with('status', 'Task changes saved.');
    }

    public function changePriority(ChangeTaskPriorityRequest $request, Task $task, ChangeTaskPriority $changePriority): RedirectResponse
    {
        $changePriority->handle($request->user(), $task, $request->validated('priority'));

        return to_route('tasks.show', $task)->with('status', 'Task priority updated.');
    }

    public function changeDueDate(ChangeTaskDueDateRequest $request, Task $task, ChangeTaskDueDate $changeDueDate): RedirectResponse
    {
        $changeDueDate->handle($request->user(), $task, $request->validated('due_on'));

        return to_route('tasks.show', $task)->with('status', 'Task due date updated.');
    }

    public function destroy(Request $request, Task $task, DeleteTask $delete): RedirectResponse
    {
        $projectId = $task->project_id;
        $delete->handle($request->user(), $task);

        return to_route('projects.show', $projectId)->with('status', 'Task deleted.');
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

        return to_route('projects.index')
            ->with('status', $keys->displayKey($task).' created.');
    }
}
