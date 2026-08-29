<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\Queries\DashboardQueryService;
use App\Domain\Identity\Services\UserPeriodResolver;
use App\Http\Requests\DashboardPeriodRequest;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardPeriodRequest $request, UserPeriodResolver $periods, DashboardQueryService $dashboard): View
    {
        $selection = $request->selection();
        $snapshot = $dashboard->for($request->user(), $periods->resolve($request->user(), $selection));

        return view('pages.dashboard.index', [
            'snapshot' => $snapshot,
            'selection' => $selection,
        ]);
    }
}
