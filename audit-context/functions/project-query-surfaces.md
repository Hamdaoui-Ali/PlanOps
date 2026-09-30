## `ProjectIndexQuery::paginate` in app/Domain/Projects/Queries/ProjectIndexQuery.php (L14-L25)

**Purpose:** Paginates projects visible to a viewer with task progress counts and index filters.

**Inputs & Assumptions:**
- `$owner` (`User|int`): viewer identity. Trust: semi-trusted internal value.
- `$filters` (array): controller-selected values; status/sort/date values are normalized by request/UI
  conventions (`ProjectIndexQuery.php:L32-L60`).
- `$perPage` (int): clamped to 1–50 (`ProjectIndexQuery.php:L22-L25`).

**Outputs & Effects:**
- Returns an `accessibleBy` project paginator with eligible/completed task counts and query string
  (`ProjectIndexQuery.php:L14-L25`). No writes.

**Block-by-Block:**

```php
// L16-L20
$query = Project::query()->accessibleBy($owner)->withCount([...]);
```
- **What:** Establishes project visibility and attaches top-level task aggregates.
- **Why here:** All index filters/sorts must operate on the viewer's project set.
- **Assumes:** relation counts use project relation and status values; `eligibleTasks` at `L27-L30` defines
  top-level/non-cancelled semantics.
- **Establishes:** viewer-scoped base query.

```php
// L22-L25
$this->applyFilters(...); $this->applySort(...); return $query->paginate(...);
```
- **What:** Applies archive/search/status/target date/sort choices and paginates.
- **Why here:** Filters refine visibility rather than replacing it.
- **Assumes:** the caller passes only expected filter shapes; invalid status values are ignored by `tryFrom`.
- **Establishes:** index result.

**Cross-Function Dependencies:**
- Callee `Project::accessibleBy` and private `eligibleTasks`, `applyFilters`, `applySort`, `orderByProgress`
  (`ProjectIndexQuery.php:L27-L85`).
- Caller `ProjectController::index` (`ProjectController.php:L26-L31`).
- Tests cover owner/foreign project index behavior (`tests/Feature/Projects/ProjectManagementTest.php:L138-L158`).

**Open Questions:**
- The `orderByProgress` subqueries use explicit `deleted_at IS NULL` while the relation counts rely on the
  model's default SoftDeletes behavior (`ProjectIndexQuery.php:L73-L85`); this is a cross-query invariant to
  keep in view.

---

## `ProjectOverviewQuery::for` in app/Domain/Projects/Queries/ProjectOverviewQuery.php (L13-L42)

**Purpose:** Re-resolves one project through viewer scope and loads top-level tasks, children, and progress
counts for the overview screen.

**Inputs & Assumptions:**
- `$owner` (`User|int`): viewer. Trust: semi-trusted.
- `$project` (`Project`): route-bound/project argument. Trust: semi-trusted; identity is narrowed by
  `whereKey` after scope (`ProjectOverviewQuery.php:L17-L19`).

**Outputs & Effects:**
- Returns a fully-loaded `Project` or throws not-found (`ProjectOverviewQuery.php:L17-L42`). No writes.

**Block-by-Block:**

```php
// L17-L19
Project::query()->accessibleBy($ownerId)->whereKey($project->getKey())
```
- **What:** Re-applies project visibility to the supplied key.
- **Why here:** Keeps the query safe for direct domain callers as well as route callers.
- **Assumes:** project key is stable and the route/caller's `$project` may be an unscoped model instance.
- **Establishes:** resolved accessible project.

```php
// L20-L42
->with([...])->withCount([...])->firstOrFail();
```
- **What:** Loads non-child tasks, children, child counts, and project counts.
- **Why here:** View data is assembled from the scoped project relation.
- **Assumes:** task relation automatically binds by `project_id`; default Task SoftDeletes applies.
- **Establishes:** overview graph.

**Cross-Function Dependencies:**
- Callee `Project::accessibleBy` (`Project.php:L92-L104`); `ProjectController::show` invokes this before
  Attention/activity queries (`ProjectController.php:L38-L54`).
- Tests cover foreign project and deleted task behavior (`tests/Feature/Projects/ProjectOverviewTest.php:L29-L91`).

**Open Questions:**
- Child task loading uses the relation's default scope and does not call `accessibleBy` on each child; project
  resolution is the assumed boundary (`ProjectOverviewQuery.php:L20-L33`).

---

## `AttentionQuery::for` in app/Domain/Attention/Queries/AttentionQuery.php (L13-L33)

**Purpose:** Finds accessible top-level tasks in one project that meet overdue, blocked, review-age, or stale
activity conditions and attaches explanatory reasons.

**Inputs & Assumptions:**
- `$owner` (`User`): viewer. Trust: trusted controller subject.
- `$project` (`Project`): already resolved project. Trust: semi-trusted.
- Implicit: `Task::accessibleBy` plus `project_id` is sufficient to bind the query to the project
  (`AttentionQuery.php:L19-L21`).

**Outputs & Effects:**
- Returns an Eloquent collection with `attention_reasons` model attributes; no writes to persistence
  (`AttentionQuery.php:L19-L33`).

**Block-by-Block:**

```php
// L19-L27
return Task::query()->accessibleBy($owner)->where('project_id', $project->getKey())->whereNull('parent_task_id') ...;
```
- **What:** Applies task visibility, project ID, top-level condition, and attention predicates.
- **Why here:** Scope precedes all attention classification.
- **Assumes:** the project object/key corresponds to a project visible to the owner; route overview supplies
  such a project.
- **Establishes:** candidate task set.

```php
// L27-L33
->get()->each(function (Task $task) ... $task->setAttribute('attention_reasons', $reasons));
```
- **What:** Loads project key and computes reasons using the same time references.
- **Why here:** The view needs both task data and human-readable reason labels.
- **Assumes:** `Task::isOverdueOn` and nullable timestamps reflect current task state.
- **Establishes:** presentation-only reason attributes.

**Cross-Function Dependencies:**
- Callee `Task::accessibleBy` and `Task::isOverdueOn` (`Task.php:L103-L105`, `L115-L124`).
- Caller `ProjectController::show` after `ProjectOverviewQuery::for` (`ProjectController.php:L38-L54`).

**Open Questions:**
- Nothing in this query independently re-resolves `$project`; its project ID precondition comes from the
  controller/query sequence.

---

## `ProjectActivityFeedQuery::for` in app/Domain/Projects/Queries/ProjectActivityFeedQuery.php (L13-L29)

**Purpose:** Loads recent role-change project events for a viewer-accessible project.

**Inputs & Assumptions:**
- `$viewer` (`User|int`), `$project` (`Project|int`), `$limit` (int): viewer/project identifiers and UI
  limit; trust is semi-trusted internal input.
- Implicit: event `project_id` binds the event to the project and `Project::accessibleBy` is the visibility
  boundary (`ProjectActivityFeedQuery.php:L18-L20`).

**Outputs & Effects:**
- Returns up to 50 role-change events with actor/subject names, newest first (`ProjectActivityFeedQuery.php:L19-L29`).
  No writes.

**Cross-Function Dependencies:**
- Callee `Project::accessibleBy` (`Project.php:L92-L104`).
- Caller `ProjectController::show` (`ProjectController.php:L38-L54`).
- `ProjectEvent` relation methods supply actor/subject (`ProjectEvent.php:L39-L52`).

**Open Questions:**
- The method accepts a raw project ID/model and does not independently compare it to a separately loaded
  project object; its `whereIn` + `where` pair is the entire project boundary (`ProjectActivityFeedQuery.php:L18-L21`).
