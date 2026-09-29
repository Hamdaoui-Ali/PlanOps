<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Queries\TeamAnalyticsQuery;
use App\Domain\Identity\Services\UserPeriodResolver;
use App\Domain\Projects\Models\Project;
use App\Http\Requests\DashboardPeriodRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ProjectTeamAnalyticsController extends Controller
{
    public function index(DashboardPeriodRequest $request, Project $project, UserPeriodResolver $periods, TeamAnalyticsQuery $analytics): View
    {
        Gate::forUser($request->user())->authorize('viewAnalytics', $project);

        $selection = $request->selection();
        $period = $periods->resolve($request->user(), $selection);

        return view('pages.projects.team-analytics', [
            'project' => $project,
            'snapshot' => $analytics->for($request->user(), $project, $period),
            'selection' => $selection,
        ]);
    }
}
