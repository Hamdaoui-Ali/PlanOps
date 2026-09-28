<?php

namespace App\Http\Controllers\Collaboration;

use App\Domain\Collaboration\Queries\TeamWorkQuery;
use App\Domain\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ProjectTeamWorkController extends Controller
{
    public function show(Request $request, Project $project, TeamWorkQuery $work): View
    {
        Gate::forUser($request->user())->authorize('viewTeamWork', $project);

        return view('pages.projects.team-work', [
            'snapshot' => $work->for($request->user(), $project),
        ]);
    }
}
