<?php

namespace App\Http\Controllers;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Queries\TaskActivityFeedQuery;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Queries\TaskKeyQuery;
use App\Http\Requests\ActivityFiltersRequest;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

final class ActivityController extends Controller
{
    public function index(ActivityFiltersRequest $request, TaskActivityFeedQuery $feed, TaskKeyQuery $keys): View
    {
        $owner = $request->user();
        $filters = $request->filters();
        $timezone = $owner->preference?->timezone ?? 'Africa/Casablanca';

        if (isset($filters['from'])) {
            $filters['from'] = CarbonImmutable::createFromFormat('Y-m-d', $filters['from'], $timezone)->startOfDay()->utc();
        }

        if (isset($filters['until'])) {
            $filters['until'] = CarbonImmutable::createFromFormat('Y-m-d', $filters['until'], $timezone)->addDay()->startOfDay()->utc();
        }

        return view('pages.activity.index', [
            'activities' => $feed->paginate($owner, $filters),
            'keys' => $keys,
            'filters' => $request->filters(),
            'projects' => Project::query()->ownedBy($owner)->orderBy('name')->get(['id', 'name', 'key']),
            'tasks' => Task::query()->ownedBy($owner)->with('project:id,key')->orderBy('project_id')->orderBy('number')->get(['id', 'project_id', 'number', 'title']),
            'eventTypes' => TaskActivityType::cases(),
        ]);
    }
}
