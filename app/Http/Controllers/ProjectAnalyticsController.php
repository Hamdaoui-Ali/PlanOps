<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Queries\AnalyticsQueryService;
use App\Domain\Identity\Services\UserPeriodResolver;
use App\Domain\Projects\Models\Project;
use App\Http\Requests\DashboardPeriodRequest;
use Illuminate\View\View;

final class ProjectAnalyticsController extends Controller
{
    public function index(DashboardPeriodRequest $request, Project $project, UserPeriodResolver $periods, AnalyticsQueryService $analytics): View
    {
        $selection = $request->selection();
        $period = $periods->resolve($request->user(), $selection);

        return view('pages.projects.analytics', ['project' => $project, 'snapshot' => $analytics->for($request->user(), $period, $project), 'selection' => $selection]);
    }
}
