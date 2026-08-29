<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\ReorderTasks;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\ProjectBoardQuery;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Http\Requests\ChangeTaskStatusRequest;
use App\Http\Requests\ReorderTasksRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ProjectBoardController extends Controller
{
    public function show(Request $request, Project $project, ProjectBoardQuery $board, TaskKeyQuery $keys): View
    {
        $includeCancelled = $request->boolean('include_cancelled');

        return view('pages.projects.board', [
            'project' => $project,
            'columns' => $board->for($request->user(), $project, $includeCancelled),
            'statuses' => TaskStatus::cases(),
            'keys' => $keys,
            'includeCancelled' => $includeCancelled,
        ]);
    }

    public function changeStatus(
        ChangeTaskStatusRequest $request,
        Project $project,
        Task $task,
        ChangeTaskStatus $changeStatus,
    ): RedirectResponse {
        abort_unless((int) $task->project_id === (int) $project->getKey(), 404);

        $changeStatus->handle($request->user(), $task, $request->validated('status'));

        return to_route('projects.board', $project)->with('status', 'Task status updated.');
    }

    public function reorder(
        ReorderTasksRequest $request,
        Project $project,
        ReorderTasks $reorder,
    ): RedirectResponse {
        $reorder->handle(
            $request->user(),
            $project,
            TaskStatus::from($request->validated('status')),
            $request->validated('ordered_task_ids'),
        );

        return to_route('projects.board', $project)->with('status', 'Board order updated.');
    }
}
