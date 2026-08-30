<?php

namespace App\Http\Controllers;

use App\Domain\Projects\Actions\ArchiveProject;
use App\Domain\Projects\Actions\ChangeProjectStatus;
use App\Domain\Projects\Actions\CreateProject;
use App\Domain\Projects\Actions\RestoreProject;
use App\Domain\Projects\Actions\UpdateProject;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Queries\ProjectIndexQuery;
use App\Domain\Projects\Queries\ProjectOverviewQuery;
use App\Domain\Attention\Queries\AttentionQuery;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Http\Requests\ChangeProjectStatusRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectIndexQuery $projects): View
    {
        return view('pages.projects.index', [
            'projects' => $projects->paginate($request->user(), $request->only(['search', 'status', 'archived', 'target_date', 'sort'])),
        ]);
    }

    public function create(): View
    {
        return view('pages.projects.create', ['statuses' => ProjectStatus::cases()]);
    }

    public function show(Request $request, Project $project, ProjectOverviewQuery $overview, AttentionQuery $attention): View
    {
        $project = $overview->for($request->user(), $project);

        return view('pages.projects.show', [
            'project' => $project,
            'statuses' => TaskStatus::cases(),
            'attentionTasks' => $attention->for($request->user(), $project),
        ]);
    }

    public function store(StoreProjectRequest $request, CreateProject $create): RedirectResponse
    {
        $project = $create->handle($request->user(), $request->validated());

        return to_route('projects.index')->with('status', 'Project created.');
    }

    public function edit(Project $project): View
    {
        return view('pages.projects.edit', ['project' => $project, 'statuses' => ProjectStatus::cases()]);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $update): RedirectResponse
    {
        $update->handle($request->user(), $project, $request->validated());

        return to_route('projects.index')->with('status', 'Project details updated.');
    }

    public function changeStatus(ChangeProjectStatusRequest $request, Project $project, ChangeProjectStatus $changeStatus): RedirectResponse
    {
        $changeStatus->handle($request->user(), $project, $request->validated('status'));

        return to_route('projects.edit', $project)->with('status', 'Project status updated.');
    }

    public function archive(Request $request, Project $project, ArchiveProject $archive): RedirectResponse
    {
        $archive->handle($request->user(), $project);

        return to_route('projects.index')->with('status', 'Project archived.');
    }

    public function restore(Request $request, Project $project, RestoreProject $restore): RedirectResponse
    {
        $restore->handle($request->user(), $project);

        return to_route('projects.edit', $project)->with('status', 'Project restored.');
    }
}
