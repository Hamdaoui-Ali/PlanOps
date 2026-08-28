<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\CreateTask;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Http\Requests\StoreTaskRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaskController extends Controller
{
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

        return to_route('projects.tasks.create', $project)
            ->with('status', $keys->displayKey($task).' created.');
    }
}
