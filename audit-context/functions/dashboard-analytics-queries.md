## `DashboardQueryService::for` in app/Domain/Dashboard/Queries/DashboardQueryService.php (L19-L66)

**Purpose:** Builds the authenticated user's dashboard snapshot from accessible current tasks, historical
activity, and active projects.

**Inputs & Assumptions:**
- `$user` (`User`): authenticated dashboard viewer. Trust: trusted controller/FormRequest subject.
- `$period` (`ReportPeriod`): validated/resolved time window. Trust: semi-trusted value object; constructor
  guarantees end after start and a supported bucket (`ReportPeriod.php:L11-L27`).
- Implicit: task and activity visibility are supplied by `accessibleBy`, and activity task relations may
  include soft-deleted rows before later filtering (`DashboardQueryService.php:L26-L48`).

**Outputs & Effects:**
- Reads tasks, activities, and projects; returns `DashboardSnapshot` containing counts and up to eight valid
  recent activity rows (`DashboardQueryService.php:L58-L65`). No writes.

**Block-by-Block:**

```php
// L26-L37
$currentTasks = Task::query()->accessibleBy($user)->whereNull('parent_task_id') ... ->get(...);
$today = CarbonImmutable::now(...)->startOfDay();
$overdueCount = ...;
```
- **What:** Counts accessible top-level current tasks by status and local overdue date.
- **Why here:** Status and overdue counts are the first part of the dashboard snapshot.
- **Assumes:** task status casts are valid and `preference->timezone` is a valid timezone or fallback is
  suitable (`DashboardQueryService.php:L36-L37`).
- **Establishes:** `statusCounts` and `overdueCount` are based on accessible non-child tasks.
- **Depended on by:** snapshot construction at `L58-L65`.

```php
// L39-L56
$activities = TaskActivity::query()->accessibleBy($user) ... ->with([...])->get();
$validActivities = $activities->filter(...);
$created = ...;
$completed = ...;
```
- **What:** Reads viewer-accessible activity in the period, loads trashed task relations, then excludes
  activity whose task is absent or trashed and counts top-level lifecycle rows.
- **Why here:** Activity rows are needed for recent activity and period balance.
- **Assumes:** activity `task_id` scope is authoritative; `newStatus` can decode stored arrays
  (`DashboardQueryService.php:L68-L73`).
- **Establishes:** `validActivities` is the collection used for both period counts and recent activity.
- **Depended on by:** snapshot output.

```php
// L58-L65
return new DashboardSnapshot(... activeProjects: Project::query()->accessibleBy($user) ...);
```
- **What:** Adds active project count and assembles the value object.
- **Why here:** All component counts have been scoped and computed.
- **Assumes:** project status/archive filters are presentation semantics, not visibility rules.
- **Establishes:** dashboard snapshot bound to the requested report period.
- **Depended on by:** `DashboardController::__invoke`.

**Cross-Function Dependencies:**
- Callees `Task::scopeAccessibleBy` (`Task.php:L115-L124`), `TaskActivity::scopeAccessibleBy`
  (`TaskActivity.php:L85-L88`), and `Project::scopeAccessibleBy` (`Project.php:L92-L104`).
- Caller `DashboardController::__invoke` (`DashboardController.php:L12-L20`).
- Tests: dashboard ownership and member scope (`tests/Feature/Dashboard/DashboardOwnershipTest.php:L10-L21`,
  `tests/Feature/Collaboration/MemberExperienceTest.php:L31-L51`).

**Open Questions:**
- The method does not use the stricter detailed-report scope; the intended dashboard visibility is ordinary
  project/task accessibility as encoded at `L26-L30`, `L39-L46`, and `L60-L60`.

---

## `AnalyticsQueryService::for` in app/Domain/Analytics/Queries/AnalyticsQueryService.php (L18-L82)

**Purpose:** Builds global or project-filtered analytics from detailed-report-visible project tasks and their
activity in a report period.

**Inputs & Assumptions:**
- `$user` (`User`): report viewer. Trust: trusted controller/FormRequest subject.
- `$period` (`ReportPeriod`): resolved value object. Trust: semi-trusted; period invariants are in
  `ReportPeriod.php:L11-L27`.
- `$project` (`Project|null`): optional controller-supplied project. Trust: semi-trusted; report visibility is
  applied through the task's project query (`AnalyticsQueryService.php:L20-L25`).

**Outputs & Effects:**
- Reads top-level tasks, in-period activities, and project relations; filters activity whose related task is
  absent/trashed; returns throughput, medians, time-in-status, and project contribution
  (`AnalyticsQueryService.php:L20-L28`, `L60-L81`). No writes.

**Block-by-Block:**

```php
// L20-L28
$tasks = Task::query()->whereHas('project', fn ($query) => $query->detailedReportsVisibleTo($user))
    ->whereNull('parent_task_id')
    ->when($project, fn ($query) => $query->where('project_id', $project->getKey()))
    ->with('project')->get();
$taskIds = $tasks->modelKeys();
$activities = TaskActivity::query()->whereIn('task_id', $taskIds) ... ->with('task')->get();
```
- **What:** Establishes the task ID set from detailed-report-visible projects, optionally narrows it to the
  supplied project, and fetches in-period activity by those IDs.
- **Why here:** All later metrics operate on one captured task/activity set.
- **Assumes:** project report scope is sufficient for task visibility; task/project foreign keys are valid;
  activity task relation is authoritative for the `trashed` check.
- **Establishes:** `tasks` and `activities` are the metric inputs.
- **Depended on by:** event categorization and the snapshot at `L30-L81`.

```php
// L39-L61
foreach ($activities as $activity) { ... }
$throughput = ...;
$completionEvents = ...;
```
- **What:** Derives distinct lifecycle event IDs and first completion events.
- **Why here:** The same task can have multiple activity rows; unique/grouped IDs define counts.
- **Assumes:** status payloads use either `status` or `value`; `status` helper decodes that shape
  (`AnalyticsQueryService.php:L84-L87`).
- **Establishes:** event collections and completion event set used for duration metrics.
- **Depended on by:** median and time-in-status helpers.

**Cross-Function Dependencies:**
- Callee `Project::scopeDetailedReportsVisibleTo` (`Project.php:L111-L126`); `status`, `median`,
  `timeInStatus`, and `projectContribution` are internal helpers (`AnalyticsQueryService.php:L84-L130`).
- Callers: `AnalyticsController::index` and `ProjectAnalyticsController::index`.
- Tests assert role and legacy/removal boundaries (`tests/Unit/Domain/Analytics/AnalyticsMetricTest.php:L18-L112`).

**Open Questions:**
- The base task query uses the project report scope instead of `Task::accessibleBy`; this is an explicit
  query contract, and the underlying project/task relation invariant is not established by this function.

---

## `TeamAnalyticsQuery::for` in app/Domain/Analytics/Queries/TeamAnalyticsQuery.php (L21-L58)

**Purpose:** Builds manager-visible analytics for one project, including status distribution, workload, and
weekly activity flow.

**Inputs & Assumptions:**
- `$viewer` (`User`), `$project` (`Project`), `$period` (`ReportPeriod`), optional `$now` timestamp.
  Viewer is trusted controller subject; project is route-bound; period is a validated value object; now is a
  testable clock input (`TeamAnalyticsQuery.php:L21-L25`).
- Implicit: a project that satisfies `detailedReportsVisibleTo` is the boundary for task/activity IDs
  (`TeamAnalyticsQuery.php:L21-L39`).

**Outputs & Effects:**
- Re-resolves the project, loads top-level task fields, loads in-period activity, and returns
  `TeamAnalyticsSnapshot` (`TeamAnalyticsQuery.php:L21-L58`). No writes.

**Block-by-Block:**

```php
// L22-L25
$project = Project::query()->detailedReportsVisibleTo($viewer)->whereKey($project->getKey())->firstOrFail();
```
- **What:** Re-resolves the supplied project through manager-level report scope.
- **Why here:** The controller policy is not the only project boundary; the query establishes its own ID.
- **Assumes:** `detailedReportsVisibleTo` is the intended Team Analytics visibility rule.
- **Establishes:** `$project` is a detailed-report-visible project.
- **Depended on by:** task/activity queries at `L28-L39`.

```php
// L28-L39
$tasks = Task::query()->where('project_id', $project->getKey())->whereNull('parent_task_id')->get(...);
$activities = $taskIds === [] ? new EloquentCollection : TaskActivity::query()->whereIn('task_id', $taskIds) ...;
```
- **What:** Limits the data set to top-level tasks and their activity for the resolved project.
- **Why here:** Team metrics are project-local and operate on task IDs captured in the same call.
- **Assumes:** project/task foreign-key integrity; no second task scope is needed after project re-resolution.
- **Establishes:** tasks and activity input collections.
- **Depended on by:** `throughput`, `statusDistribution`, `currentWorkload`, `weeklyFlow` at `L41-L57`.

**Cross-Function Dependencies:**
- Callee `Project::scopeDetailedReportsVisibleTo` (`Project.php:L111-L126`).
- Internal helpers use task status/activity enum values (`TeamAnalyticsQuery.php:L60-L212`).
- Caller `ProjectTeamAnalyticsController::index` (`ProjectTeamAnalyticsController.php:L14-L25`).
- Tests cover foreign project, deleted/subtask, removed/member route behavior
  (`tests/Unit/Domain/Analytics/TeamAnalyticsQueryTest.php:L120-L213`,
  `tests/Feature/Analytics/TeamAnalyticsScreenTest.php:L80-L107`).

**Open Questions:**
- The task query does not repeat `Task::accessibleBy` after project re-resolution; the project/task
  referential invariant is assumed and not established here.
