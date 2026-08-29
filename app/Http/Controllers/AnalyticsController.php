<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\Queries\AnalyticsQueryService;
use App\Domain\Identity\Services\UserPeriodResolver;
use App\Http\Requests\DashboardPeriodRequest;
use Illuminate\View\View;

final class AnalyticsController extends Controller
{
    public function index(DashboardPeriodRequest $request, UserPeriodResolver $periods, AnalyticsQueryService $analytics): View
    {
        $selection = $request->selection();
        $period = $periods->resolve($request->user(), $selection);

        return view('pages.analytics.index', ['snapshot' => $analytics->for($request->user(), $period), 'selection' => $selection]);
    }
}
