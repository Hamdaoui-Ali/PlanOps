## `SearchQueryService::search` in app/Domain/Search/Queries/SearchQueryService.php (L14-L53)

**Purpose:** Finds accessible tasks and projects by normalized free-text term.

**Inputs & Assumptions:**
- `$user` (`User`): authenticated viewer. Trust: trusted controller/FormRequest subject.
- `$term` (string): validated search term. Trust: semi-trusted request input; `SearchRequest` trims and caps
  it (`SearchRequest.php:L14-L27`).
- Implicit: raw `LIKE` clauses use bound `$needle`; task/project/label scopes provide visibility
  (`SearchQueryService.php:L21-L50`).

**Outputs & Effects:**
- Returns up to 20 tasks and 20 projects plus the trimmed term; returns empty collections for terms shorter
  than two characters (`SearchQueryService.php:L16-L18`, `L21-L52`). No writes.

**Block-by-Block:**

```php
// L16-L18
$term = trim($term);
if (mb_strlen($term) < 2) { return [...]; }
```
- **What:** Normalizes and rejects short terms.
- **Why here:** Avoids query construction for empty/short search input.
- **Assumes:** caller already validated the type/maximum length; `trim` is sufficient normalization.
- **Establishes:** `$term` is non-short and `$needle` can be built.

```php
// L21-L39
$tasks = Task::query()->accessibleBy($user)->with(['project', 'labels'])->where(function (...) { ... });
```
- **What:** Searches task fields, derived task key, project name/key, and accessible labels.
- **Why here:** The base task scope is applied before the OR search group.
- **Assumes:** all OR branches remain inside the preceding `where` closure; labels are independently scoped
  through `Label::accessibleBy`.
- **Establishes:** task result rows are selected from the viewer task scope and limited to 20.
- **Depended on by:** returned `tasks` and `SearchController::index`.

```php
// L41-L50
$projects = Project::query()->accessibleBy($user)->where(...)->limit(20)->get();
```
- **What:** Searches accessible project names/keys and limits the project result set.
- **Why here:** Project results are a separate result type with its own scope.
- **Assumes:** project scope is sufficient for the project result payload.
- **Establishes:** viewer-scoped project result collection.

**Cross-Function Dependencies:**
- Callees `Task::accessibleBy`, `Project::accessibleBy`, and `Label::accessibleBy`.
- Caller `SearchController::index` (`SearchController.php:L11-L19`).
- Tests cover text fields, label/project matching, foreign project exclusion, deleted task exclusion, cap,
  and short terms (`tests/Feature/Search/SearchQueryTest.php:L10-L65`).

**Open Questions:**
- The raw subquery for task keys reads `projects` by ID without an explicit visibility predicate
  (`SearchQueryService.php:L28-L28`); the outer task scope is the visibility boundary assumed by this
  derived display-key search.

---

## `TaskActivityFeedQuery::paginate` in app/Domain/Activity/Queries/TaskActivityFeedQuery.php (L14-L37)

**Purpose:** Paginates the global activity feed with validated type/date filters and visible task IDs.

**Inputs & Assumptions:**
- `$owner` (`User|int`): feed viewer. Trust: semi-trusted internal identity.
- `$filters` (array): FormRequest-validated filter values; project/task IDs are integer-validated and event
  type is enum-limited (`ActivityFiltersRequest.php:L23-L33`).
- `$perPage` (int): internal/UI value clamped to 1–50 (`TaskActivityFeedQuery.php:L19-L21`).

**Outputs & Effects:**
- Returns a paginated activity collection with task/project relations, filters, and descending time/id order
  (`TaskActivityFeedQuery.php:L23-L36`). No writes.

**Block-by-Block:**

```php
// L23-L24
TaskActivity::query()->whereIn('task_id', Task::withTrashed()->accessibleBy($owner)->select('id'))
```
- **What:** Builds the task-ID visibility boundary and includes soft-deleted tasks for historical feed rows.
- **Why here:** This predicate precedes caller-supplied project/task filters.
- **Assumes:** `task_id` binds activity to the task whose visibility is evaluated.
- **Establishes:** base visible task ID set.

```php
// L25-L36
->with([...])->when(...)->orderByDesc(...)->paginate($perPage);
```
- **What:** Loads limited relations and applies optional project, task, event, and UTC date bounds.
- **Why here:** Filter values refine the already-scoped base query.
- **Assumes:** activity/project/task foreign-key consistency and validated dates supplied by the controller.
- **Establishes:** paginated feed result.

**Cross-Function Dependencies:**
- Callee `Task::accessibleBy` (`Task.php:L115-L124`) with `withTrashed`.
- Caller `ActivityController::index` (`ActivityController.php:L16-L38`).
- Tests assert owner feed filters and foreign activity exclusion (`tests/Feature/Activity/GlobalActivityFeedTest.php:L10-L59`).

**Open Questions:**
- The optional `project_id` and `task_id` filters are raw equality constraints after the task-ID subquery;
  the method relies on the activity foreign-key relationship to keep them aligned.

---

## `TaskActivityFeedQuery::forTask` in app/Domain/Activity/Queries/TaskActivityFeedQuery.php (L39-L53)

**Purpose:** Loads chronological activity history for one visible task, including history for a soft-deleted
task.

**Inputs & Assumptions:**
- `$owner` (`User|int`): viewer. Trust: semi-trusted.
- `$task` (`Task|int`): task model/ID. Trust: semi-trusted; caller should have resolved it through route or
  detail scope.
- Implicit: task-ID scope plus `where('task_id', $taskId)` is sufficient to bind the requested history
  (`TaskActivityFeedQuery.php:L43-L45`).

**Outputs & Effects:**
- Returns activity rows with project/task display relations ordered ascending by creation/id
  (`TaskActivityFeedQuery.php:L43-L52`). No writes.

**Cross-Function Dependencies:**
- Callee `Task::withTrashed()->accessibleBy` (`Task.php:L115-L124`).
- Callers `TaskController::show` and any direct domain tests.

**Open Questions:**
- Nothing in this method checks that the passed `Task` object is the same persisted row as `$taskId` when a
  model instance is supplied; it uses only `getKey()` (`TaskActivityFeedQuery.php:L41-L45`).

---

## `MyWorkQuery::paginate` in app/Domain/Tasks/Queries/MyWorkQuery.php (L15-L61)

**Purpose:** Paginates work assigned to, legacy-owned by, or manager-visible to the current user.

**Inputs & Assumptions:**
- `$owner` (`User`): authenticated My Work viewer. Trust: trusted controller subject.
- `$filters` (array): validated filters from `MyWorkFiltersRequest` (`MyWorkFiltersRequest.php:L31-L54`).
- `$perPage` (int): clamped to 1–50 (`MyWorkQuery.php:L16-L18`).
- Implicit: manager visibility is an OWNER/ADMIN role predicate over projects; legacy unassigned work uses
  task/user identity plus no project memberships (`MyWorkQuery.php:L21-L45`).

**Outputs & Effects:**
- Returns a paginator with project/label/assignee relations, child counts, validated filters, due-date
  filter, and sort order (`MyWorkQuery.php:L46-L61`). No writes.

**Block-by-Block:**

```php
// L21-L45
$query = Task::query()->accessibleBy($owner)->where(function (Builder $tasks) use ($owner): void { ... });
```
- **What:** Intersects ordinary task visibility with assigned, legacy-owner, and manager branches.
- **Why here:** A task must first be readable; the inner branches decide which readable work belongs in My Work.
- **Assumes:** direct assignee IDs refer to users in the same collaboration context; manager branch role
  predicates are equivalent to policy role rules.
- **Establishes:** base My Work task set.

```php
// L46-L61
->with(...)->withCount(...)->when(...); $this->applyDueFilter(...); $this->applySort(...); return ...;
```
- **What:** Adds presentation data, filter constraints, due-date and sort behavior.
- **Why here:** Filters refine the scoped set after identity predicates.
- **Assumes:** label filter's `Label::accessibleBy` relation limits label IDs to visible labels.
- **Establishes:** paginated result with query string.

**Cross-Function Dependencies:**
- Callees `Task::accessibleBy` and `Label::accessibleBy`; private due/sort helpers at `L63-L89`.
- Caller `MyWorkController::index` (`MyWorkController.php:L19-L54`).
- Tests cover foreign/deleted tasks, assignment, manager/member role behavior, and filters.

**Open Questions:**
- The manager predicate is repeated in `MyWorkController::index` (`MyWorkController.php:L27-L46`), so the
  controller empty-state boolean and this query depend on two copies remaining equivalent.
