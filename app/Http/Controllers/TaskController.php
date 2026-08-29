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

        if ($request->query('return_context') === 'my-work') {
            return to_route('my-work', $this->safeMyWorkQuery($request))->with('status', 'Task status updated.');
        }

        if ($request->query('return_context') === 'project-tasks') {
            return to_route('projects.tasks.index', ['project' => $task->project_id, ...$this->safeProjectTaskListQuery($request)])->with('status', 'Task status updated.');
        }

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

        if ($request->query('return_context') === 'my-work') {
            return to_route('my-work', $this->safeMyWorkQuery($request))->with('status', 'Task priority updated.');
        }

        if ($request->query('return_context') === 'project-tasks') {
            return to_route('projects.tasks.index', ['project' => $task->project_id, ...$this->safeProjectTaskListQuery($request)])->with('status', 'Task priority updated.');
        }

        return to_route('tasks.show', $task)->with('status', 'Task priority updated.');
    }

    /** @return array<string, string> */
    private function safeMyWorkQuery(Request $request): array
    {
        $safe = [];
        $allowed = [
            'status' => array_column(TaskStatus::cases(), 'value'),
            'priority' => array_column(TaskPriority::cases(), 'value'),
            'due' => ['overdue', 'today', 'this_week', 'no_due_date'],
            'sort' => ['updated', 'created', 'priority', 'due', 'task_key', 'project'],
        ];

        foreach (['project', 'label'] as $key) {
            $value = $request->query($key);
            if (is_scalar($value) && ctype_digit((string) $value)) {
                $safe[$key] = (string) $value;
            }
        }

        foreach ($allowed as $key => $values) {
            $value = $request->query($key);
            if (is_scalar($value) && in_array((string) $value, $values, true)) {
                $safe[$key] = (string) $value;
            }
        }

        foreach (['created_from', 'created_until', 'updated_from', 'updated_until'] as $key) {
            $value = $request->query($key);
            if (is_scalar($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1) {
                $safe[$key] = (string) $value;
            }
        }

        return $safe;
    }

    /** @return array<string, string> */
    private function safeProjectTaskListQuery(Request $request): array
    {
        $safe = [];
        $allowed = [
            'status' => array_column(TaskStatus::cases(), 'value'),
            'priority' => array_column(TaskPriority::cases(), 'value'),
            'due' => ['overdue', 'today', 'this_week', 'no_due_date'],
            'sort' => ['updated', 'created', 'priority', 'due', 'task_key'],
        ];

        foreach ($allowed as $key => $values) {
            $value = $request->query($key);
            if (is_scalar($value) && in_array((string) $value, $values, true)) {
                $safe[$key] = (string) $value;
            }
        }

        $label = $request->query('label');
        if (is_scalar($label) && ctype_digit((string) $label)) {
            $safe['label'] = (string) $label;
        }

        return $safe;
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
