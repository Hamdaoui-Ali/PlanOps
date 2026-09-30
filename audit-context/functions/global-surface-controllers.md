## `DashboardController::__invoke` in app/Http/Controllers/DashboardController.php (L12-L20)

**Purpose:** Resolves the selected dashboard period and renders the current user's dashboard snapshot.

**Inputs & Assumptions:**
- `DashboardPeriodRequest`: authenticated/validated request (`DashboardPeriodRequest.php:L10-L33`).
- `UserPeriodResolver`, `DashboardQueryService`: internal services.
- Implicit: route `auth` middleware supplies the same user seen by the request and query
  (`routes/web.php:L32-L34`).

**Outputs & Effects:**
- Returns dashboard view with selection and scoped snapshot (`DashboardController.php:L12-L20`). No writes.

**Block-by-Block:**

```php
// L14-L18
$selection = $request->selection();
$snapshot = $dashboard->for($request->user(), $periods->resolve(...));
```
- **What:** Bridges validated request selection to user-local period and scoped query.
- **Why here:** Period resolution precedes data collection.
- **Assumes:** query service applies data visibility; controller does not add another scope.
- **Establishes:** snapshot tied to authenticated user and selected period.

**Cross-Function Dependencies:** `DashboardPeriodRequest::selection` (`DashboardPeriodRequest.php:L26-L33`),
`UserPeriodResolver::resolve`, `DashboardQueryService::for`.

**Open Questions:**
- No controller-local deactivated-user check; nothing found in `DashboardController.php:L12-L20`.

---

## `ActivityController::index` in app/Http/Controllers/ActivityController.php (L16-L38)

**Purpose:** Converts local date filters to UTC, loads the global activity feed, and supplies scoped project/task
filter options.

**Inputs & Assumptions:**
- `ActivityFiltersRequest`: authenticated and validated filter input (`ActivityFiltersRequest.php:L11-L36`).
- `$feed`, `$keys`: internal query/formatter services.
- Implicit: owner preference timezone is valid or fallback is used (`ActivityController.php:L19-L27`).

**Outputs & Effects:**
- Returns activity view with paginated feed, scoped projects/tasks, event types, and filters
  (`ActivityController.php:L29-L38`). No writes.

**Block-by-Block:**

```php
// L19-L27
$owner = $request->user(); $filters = $request->filters(); $timezone = ...;
if (isset($filters['from'])) { ... ->utc(); }
if (isset($filters['until'])) { ... ->utc(); }
```
- **What:** Converts inclusive local date bounds to UTC half-open timestamps.
- **Why here:** Feed query consumes stored timestamps in UTC.
- **Assumes:** date format validation and timezone fallback make `createFromFormat` successful.
- **Establishes:** query filter time bounds.

```php
// L29-L38
'activities' => $feed->paginate($owner, $filters),
'projects' => Project::query()->accessibleBy($owner)...,
'tasks' => Task::query()->accessibleBy($owner)...,
```
- **What:** Passes the same user to feed and filter option queries.
- **Why here:** UI options must describe the same visible data set as the feed.
- **Assumes:** feed's task-ID scope and raw project/task filter values agree on activity foreign keys.
- **Establishes:** view data.

**Cross-Function Dependencies:** `ActivityFiltersRequest`, `TaskActivityFeedQuery`, `Project::accessibleBy`,
`Task::accessibleBy`, `TaskKeyQuery`.

**Open Questions:**
- Nothing in the controller independently validates `project_id`/`task_id` belong to the same project; the
  feed query's task-ID boundary is the intended cross-field rule.

---

## `SearchController::index` in app/Http/Controllers/SearchController.php (L11-L19)

**Purpose:** Passes the validated term and authenticated viewer to the search query and renders result data.

**Inputs & Assumptions:**
- `SearchRequest`: authenticated, trimmed, max-100 query (`SearchRequest.php:L9-L27`).
- `$search`: internal `SearchQueryService`.

**Outputs & Effects:**
- Returns search view with task/project collections and `searched` flag (`SearchController.php:L11-L19`). No writes.

**Cross-Function Dependencies:** `SearchRequest::term`, `SearchQueryService::search`, route `GET /search`
(`routes/web.php:L37-L39`).

**Open Questions:**
- Controller does not add a separate query scope; all visibility is delegated to `SearchQueryService`.

---

## `AnalyticsController::index` in app/Http/Controllers/AnalyticsController.php (L12-L18)

**Purpose:** Resolves a user-local period and renders global detailed analytics.

**Inputs & Assumptions:**
- `DashboardPeriodRequest`, `UserPeriodResolver`, `AnalyticsQueryService`: authenticated request and internal
services.

**Outputs & Effects:**
- Returns analytics view with selection and user-scoped report snapshot (`AnalyticsController.php:L12-L18`).
  No writes.

**Cross-Function Dependencies:** `DashboardPeriodRequest::selection`, `UserPeriodResolver::resolve`,
`AnalyticsQueryService::for`.

**Open Questions:**
- Global analytics has no project route parameter; its report boundary is entirely the query service's
  `detailedReportsVisibleTo` predicate.

---

## `ExportController::activity` in app/Http/Controllers/ExportController.php (L36-L67)

**Purpose:** Streams exportable activity as JSON or CSV based on the route format.

**Inputs & Assumptions:**
- `ExportRequest`: `exportAny` policy already authorized the user (`ExportRequest.php:L11-L14`).
- `$exports`: internal export query service.
- `format`: route-constrained to csv/json (`routes/web.php:L42`).

**Outputs & Effects:**
- Streams a JSON array or CSV response and serializes activity rows; no persistence writes
  (`ExportController.php:L36-L67`).

**Block-by-Block:**

```php
// L37-L51
if (($request->route('format') ?? ...) === 'json') { ... foreach ($exports->activity(request()->user()) ...); }
```
- **What:** Selects JSON mode and serializes each export query row.
- **Why here:** Format is a route/request concern; data scope is delegated to the query service.
- **Assumes:** route `whereIn` and request validation constrain format.
- **Establishes:** JSON stream framing and row sequence.

```php
// L53-L62
foreach ($exports->activity(request()->user()) as $activity) { ... }
```
- **What:** Streams CSV rows using `activityRow`.
- **Why here:** Keeps memory bounded through lazy query iteration.
- **Assumes:** loaded project/task relations are coherent for `activityRow`.
- **Establishes:** CSV export.

**Cross-Function Dependencies:** `ExportQueryService::activity`, `activityRow` (`ExportController.php:L64-L67`),
`ExportRequest::authorize`.

**Open Questions:**
- The stream does not add a second row-by-row authorization check; it relies on `ExportQueryService::activity`.
