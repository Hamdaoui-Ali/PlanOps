<?php

namespace App\Http\Controllers\Collaboration;

use App\Http\Controllers\Controller;
use App\Domain\Projects\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectTeamController extends Controller
{
    public function show(Request $request, Project $project): View
    {
        Gate::forUser($request->user())->authorize('view', $project);
        return view('pages.projects.team', ['project' => $project->load(['activeMemberships.user', 'invitations' => fn ($q) => $q->whereNull('accepted_at')->whereNull('revoked_at')->latest()])]);
    }
}
