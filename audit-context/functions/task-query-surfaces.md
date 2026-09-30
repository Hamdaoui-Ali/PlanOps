## `ProjectBoardQuery::for` in app/Domain/Tasks/Queries/ProjectBoardQuery.php (L18-L58)

**Purpose:** Re-resolves an accessible project and returns its top-level tasks grouped by status for the board.

**Inputs & Assumptions:**
- `$owner` (`User|int`): viewer. Trust: semi-trusted.
- `$project` (`Project`): supplied project model; narrowed by accessible key (`ProjectBoardQuery.php:L22-L27`).
- `$includeCancelled` (bool): UI flag. Trust: semi-trusted but type-normalized by caller.

**Outputs & Effects:**
- Reads an accessible project and task graph with labels/assignee/children/counts; returns a status-keyed array
  (`ProjectBoardQuery.php:L22-L58`). No writes.

**Block-by-Block:**

```php
// L22-L27
$ownedProject = Project::query()->accessibleBy($ownerId)->whereKey($project->getKey())->firstOrFail();
```
- **What:** Establishes the board project boundary.
- **Why here:** Task query below uses the resolved project key.
- **Assumes:** caller's project key is stable.
- **Establishes:** accessible project model.

```php
// L32-L51
$tasks = Task::query()->accessibleBy($ownerId)->where('project_id', $ownedProject->getKey()) ... ->get();
```
- **What:** Selects accessible top-level tasks for the project, optionally excluding cancelled rows, with
  nested children/counts.
- **Why here:** The board requires task and project scope independently.
- **Assumes:** child relation remains within the resolved project through task foreign keys.
- **Establishes:** task collection for grouping.

**Cross-Function Dependencies:**
- Callees `Project::accessibleBy`, `Task::accessibleBy`, status enum, task relations.
- Caller `ProjectBoardController::show` (`ProjectBoardController.php:L20-L31`).
- Tests cover foreign board and status grouping (`tests/Feature/Projects/ProjectBoardTest.php:L35-L93`).

**Open Questions:**
- Child relation loads do not add a second explicit project predicate; project/task relational integrity is
  assumed (`ProjectBoardQuery.php:L37-L46`).

---

## `ProjectTaskListQuery::paginate` in app/Domain/Tasks/Queries/ProjectTaskListQuery.php (L15-L39)

**Purpose:** Paginates tasks for one accessible project with status/priority/assignee/label/due/sort filters.

**Inputs & Assumptions:**
- `$owner` (`User`): authenticated viewer. Trust: trusted controller subject.
- `$project` (`Project`): route-bound/project input. Trust: semi-trusted; re-resolved at `L17`.
- `$filters` (array): validated by `ProjectTaskListFiltersRequest` (`ProjectTaskListFiltersRequest.php:L25-L44`).
- Implicit: task `project_id` plus `Task::accessibleBy` binds rows to the resolved project
  (`ProjectTaskListQuery.php:L22-L24`).

**Outputs & Effects:**
- Returns a paginated task collection with project/parent/label/assignee relations and child counts
  (`ProjectTaskListQuery.php:L22-L39`). No writes.

**Block-by-Block:**

```php
// L17-L24
$project = Project::query()->accessibleBy($owner)->whereKey($project->getKey())->firstOrFail();
$query = Task::query()->accessibleBy($owner)->where('project_id', $project->getKey()) ...;
```
- **What:** Re-resolves project and scopes the task base query.
- **Why here:** Direct callers cannot rely only on route binding.
- **Assumes:** project/task foreign-key relation is stable.
- **Establishes:** project-local accessible task set.

```php
// L25-L39
->when(... filters ...); $this->applyDueFilter(...); $this->applySort(...); return ...;
```
- **What:** Applies validated filters and returns the paginator.
- **Why here:** Filter values refine the scoped task set.
- **Assumes:** label ID filter uses `Label::accessibleBy` but relation `whereKey` plus task/project scope
  supplies the cross-entity condition.
- **Establishes:** filtered task list.

**Cross-Function Dependencies:**
- Callees `Project::accessibleBy`, `Task::accessibleBy`, `Label::accessibleBy`, private filter/sort helpers.
- Caller `ProjectTaskListController::index` (`ProjectTaskListController.php:L17-L42`).
- Tests cover foreign project, labels, assignee, filters (`tests/Feature/Tasks/ProjectTaskListTest.php:L62-L148`).

**Open Questions:**
- Request validation permits any existing user ID for `assignee` (`ProjectTaskListFiltersRequest.php:L30-L31`);
  the query treats it as a task `assignee_id` equality and does not require active project membership.

---

## `TaskDetailQuery::for` in app/Domain/Tasks/Queries/TaskDetailQuery.php (L11-L27)

**Purpose:** Re-resolves one task through viewer scope and loads the project/team/parent/children detail graph.

**Inputs & Assumptions:**
- `$owner` (`User|int`): viewer. Trust: semi-trusted.
- `$task` (`Task`): route/domain task model. Trust: semi-trusted; key is narrowed after `accessibleBy`
  (`TaskDetailQuery.php:L15-L17`).

**Outputs & Effects:**
- Returns one accessible task or not-found with project owners/users, active memberships, parent, and ordered
  children (`TaskDetailQuery.php:L15-L27`). No writes.

**Cross-Function Dependencies:**
- Callee `Task::accessibleBy` (`Task.php:L115-L124`) and task relations.
- Caller `TaskController::show` (`TaskController.php:L42-L53`).
- The task activity history and display key are separate callees in that controller.

**Open Questions:**
- The eager-loaded parent/children relations are not individually scoped with `accessibleBy`; the task's
  project relation and foreign-key integrity are the assumed boundary (`TaskDetailQuery.php:L18-L25`).
