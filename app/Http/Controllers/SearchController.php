<?php

namespace App\Http\Controllers;

use App\Domain\Search\Queries\SearchQueryService;
use App\Http\Requests\SearchRequest;
use Illuminate\View\View;

final class SearchController extends Controller
{
    public function index(SearchRequest $request, SearchQueryService $search): View
    {
        $results = $search->search($request->user(), $request->term());

        return view('pages.search.index', [
            ...$results,
            'searched' => $request->filled('q'),
        ]);
    }
}
