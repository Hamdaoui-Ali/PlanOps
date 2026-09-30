## `ProjectController::show` in app/Http/Controllers/ProjectController.php (L38-L54)

**Purpose:** Renders a project overview plus attention and project-event activity for an accessible project.

**Inputs & Assumptions:**
- `$project` (`Project`): custom-bound accessible project (`routes/web.php:L44-L46`).
- `$request`: authenticated viewer.
- `$overview`, `$attention`, `$activity`: internal query objects.

**Outputs & Effects:**
- Replaces the route model with `ProjectOverviewQuery::for` output and renders the overview view
  (`ProjectController.php:L38-L54`). No writes.

**Block-by-Block:**

```php
// L45-L46
$project = $overview->for($request->user(), $project);
```
- **What:** Re-applies project accessibility and loads the overview graph.
- **Why here:** Downstream queries receive the re-resolved model.
- **Assumes:** overview query's returned project is the shared project context.
- **Establishes:** accessible project model for view/child queries.

```php
// L48-L54
'attentionTasks' => $attention->for(...), 'activity' => $activity->for(...)
```
- **What:** Loads task attention and role-change events using the authenticated user and project.
- **Why here:** Both secondary surfaces are derived from the same resolved project.
- **Assumes:** child query methods apply their own task/event scopes.
- **Establishes:** overview payload.

**Cross-Function Dependencies:** `ProjectOverviewQuery::for`, `AttentionQuery::for`,
`ProjectActivityFeedQuery::for`, route project binding.

**Open Questions:**
- `ProjectController::edit` accepts the route-bound project and renders without explicit policy in the
  controller (`ProjectController.php:L63-L66`); the route binder supplies accessibility, while mutation
  authorization is in `UpdateProjectRequest`/Action.

---

## `ProjectTaskListController::index` in app/Http/Controllers/ProjectTaskListController.php (L17-L42)

**Purpose:** Renders a project task list with filtered tasks, project label options, and active assignees.

**Inputs & Assumptions:**
- `ProjectTaskListFiltersRequest`: authenticated/validated filter set.
- `$project`: custom-bound accessible project (`routes/web.php:L61-L62`).
- `$tasks`, `$keys`: internal query/formatter services.

**Outputs & Effects:**
- Returns the project task view with task paginator, filter options, labels, active member users, status, and
  priority enums (`ProjectTaskListController.php:L17-L42`). No writes.

**Block-by-Block:**

```php
// L20-L23
$owner = $request->user(); $filters = $request->filters(); 'tasks' => $tasks->paginate(...)
```
- **What:** Passes owner/project/filter context to the query object.
- **Why here:** Query object independently re-resolves the project.
- **Assumes:** route-bound project and query re-resolution refer to the same key.
- **Establishes:** task list result.

```php
// L26-L36
'hasAnyTasks' => Task::query()->accessibleBy($owner)->where('project_id', ...),
'labels' => Label::query()->where(function (...) { project_id ... or legacy user_id ... }),
'assignees' => $project->activeMemberships()->with(...)
```
- **What:** Supplies empty-state, label, and assignee options.
- **Why here:** UI filters are loaded alongside the list.
- **Assumes:** project was accessible via route binding; local label predicate is equivalent to intended project
  and legacy-label scope; active memberships are the valid assignee set.
- **Establishes:** auxiliary view data.

**Cross-Function Dependencies:** `ProjectTaskListQuery::paginate`, `Task::accessibleBy`,
`Project::activeMemberships`, `ProjectTaskListFiltersRequest`.

**Open Questions:**
- The local label predicate is not `Label::accessibleBy`; see dossier open questions and the query record.

---

## `ProjectBoardController::changeStatus` in app/Http/Controllers/ProjectBoardController.php (L33-L43)

**Purpose:** Handles status transitions submitted from a project board task route.

**Inputs & Assumptions:**
- `ChangeTaskStatusRequest`: route task authorization and status validation (`ChangeTaskStatusRequest.php:L12-L23`).
- `$project`, `$task`: custom-bound records; route additionally uses `scopeBindings()` (`routes/web.php:L78-L80`).
- `$changeStatus`: domain Action.

**Outputs & Effects:**
- Aborts with 404 when task/project IDs differ, delegates mutation, then redirects to the board
  (`ProjectBoardController.php:L33-L43`).

**Block-by-Block:**

```php
// L39-L40
abort_unless((int) $task->project_id === (int) $project->getKey(), 404);
```
- **What:** Reasserts nested identity.
- **Why here:** The route contains two identifiers and the controller makes their relationship explicit.
- **Assumes:** integer key comparison represents the route relationship.
- **Establishes:** task belongs to the route project for the action call.

```php
// L41-L43
$changeStatus->handle(...); return to_route(...);
```
- **What:** Delegates policy/action state change and redirects.
- **Why here:** Action owns the lock, scope, validation, activity record, and state writes.
- **Assumes:** FormRequest authorization and Action authorization use the same task model context.
- **Establishes:** redirect only after Action returns.

**Cross-Function Dependencies:** route custom binding, `scopeBindings`, `ChangeTaskStatusRequest`,
`ChangeTaskStatus::handle`.

**Open Questions:**
- Nothing in the controller itself loads a fresh task after the relation check; the Action's scoped lock query
  is the later state boundary.
