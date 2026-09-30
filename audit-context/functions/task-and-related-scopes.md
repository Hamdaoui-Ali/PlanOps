## `Task::scopeAccessibleBy` in app/Domain/Tasks/Models/Task.php (L115-L124)

**Purpose:** Defines task visibility for route binding, list/detail/query surfaces, activity, and mutation
re-fetches.

**Inputs & Assumptions:**
- `$viewer` (`User|int`): viewer identity. Trust: semi-trusted; callers pass authenticated users or IDs.
- Implicit: task's `project` relation, project accessibility, legacy `tasks.user_id`, and whether the task's
  project has any memberships (`Task.php:L119-L123`).

**Outputs & Effects:**
- Returns the builder with two OR branches: accessible project, or legacy owner on a project with no
  memberships (`Task.php:L119-L123`). No writes.

**Block-by-Block:**

```php
// L117-L123
$viewerId = $viewer instanceof User ? $viewer->getKey() : $viewer;

return $query->where(function (Builder $tasks) use ($viewer, $viewerId): void {
    $tasks->whereHas('project', fn (Builder $projects): Builder => $projects->accessibleBy($viewer))
        ->orWhere(fn (Builder $legacy): Builder => $legacy->where('user_id', $viewerId)
            ->whereHas('project', fn (Builder $projects): Builder => $projects->whereDoesntHave('memberships')));
});
```
- **What:** Uses project visibility as the ordinary branch and raw `user_id` only in the no-membership
  legacy branch.
- **Why here:** Task visibility follows the project boundary while retaining pre-membership task data.
- **Assumes:** A task with a project relation is governed by that project; the legacy branch is meaningful
  only when no membership row exists.
- **Establishes:** A task ID returned by the scope belongs to the viewer's readable task set.
- **Depended on by:** Route task binding, `TaskActivity::scopeAccessibleBy`, search, dashboard, My Work,
  project task/board/detail queries, and mutation Actions.

**Cross-Function Dependencies:**
- Callee `Project::scopeAccessibleBy` (internal, `Project.php:L92-L104`) and `project`/`memberships`
  relations (`Task.php:L78-L90`).
- Callers: `routes/web.php:L48-L50`, activity/dashboard/search/query classes, and task Actions.
- Shared state: `tasks.user_id`, `tasks.project_id`, `project_memberships`.

**Open Questions:**
- Soft-deleted tasks are excluded unless a caller adds `withTrashed()`; the scope itself does not alter the
  model's SoftDeletes behavior (`Task.php:L24-L26`, `L115-L124`).
- No deactivated-user predicate is present; nothing found in `Task.php:L115-L124` establishes that state.

---

## `Label::scopeAccessibleBy` in app/Domain/Labels/Models/Label.php (L51-L59)

**Purpose:** Defines label visibility for project labels and legacy personal labels.

**Inputs & Assumptions:**
- `$viewer` (`User|int`): viewer identity. Trust: semi-trusted.
- Implicit: project-scoped labels follow project accessibility; labels with null `project_id` are personal
  labels governed by `labels.user_id` (`Label.php:L53-L57`).

**Outputs & Effects:**
- Returns the builder with project-accessible OR null-project/user-owned branches (`Label.php:L55-L58`).
  No writes.

**Block-by-Block:**

```php
// L55-L58
return $query->where(function (Builder $labels) use ($viewer, $viewerId): void {
    $labels->whereHas('project', fn (Builder $projects): Builder => $projects->accessibleBy($viewer))
        ->orWhere(fn (Builder $legacy): Builder => $legacy->whereNull('project_id')->where('user_id', $viewerId));
});
```
- **What:** Couples project label visibility to project scope and keeps the legacy owner branch explicit.
- **Why here:** Labels are filter and mutation inputs on task surfaces.
- **Assumes:** `project_id IS NULL` identifies a personal/legacy label; project labels have a valid project
  relation.
- **Establishes:** A returned label is associated with an accessible project or is the viewer's personal
  label.
- **Depended on by:** Search label matching, My Work/project list filters, label Actions, and label UI option
  lists.

**Cross-Function Dependencies:**
- Callee `Project::scopeAccessibleBy` (internal, `Project.php:L92-L104`).
- Callers include `SearchQueryService`, `MyWorkQuery`, `AttachLabelToTask`, `DetachLabelFromTask`, and
  `DeleteLabel`.

**Open Questions:**
- The project task list controller has a local project-or-legacy label predicate rather than calling this scope
  (`ProjectTaskListController.php:L29-L36`); whether this is an intentional equivalent is carried in the
  dossier open questions.

---

## `TaskActivity::scopeAccessibleBy` in app/Domain/Activity/Models/TaskActivity.php (L85-L88)

**Purpose:** Derives activity visibility from task visibility rather than trusting the activity row's own
`user_id` field.

**Inputs & Assumptions:**
- `$viewer` (`User|int`): viewer identity. Trust: semi-trusted.
- Implicit: `task_id` points to a task that `Task::accessibleBy` can resolve; activity may retain a soft-deleted
  task through the relation at `TaskActivity.php:L73-L76`.

**Outputs & Effects:**
- Adds a task-ID subquery to the activity builder (`TaskActivity.php:L85-L88`). No writes.

**Block-by-Block:**

```php
// L85-L88
public function scopeAccessibleBy(Builder $query, User|int $viewer): Builder
{
    return $query->whereIn('task_id', Task::query()->accessibleBy($viewer)->select('id'));
}
```
- **What:** Uses the task scope as the activity boundary.
- **Why here:** Historical activity rows keep `user_id`, `project_id`, and `task_id`; task visibility is the
  shared collaboration rule used by feed readers.
- **Assumes:** Activity `task_id` is consistent with the related task's project.
- **Establishes:** Activity rows are limited to visible task IDs; no independent `user_id` ownership is
  asserted.
- **Depended on by:** Dashboard and any direct `TaskActivity::accessibleBy` callers.

**Cross-Function Dependencies:**
- Callee `Task::scopeAccessibleBy` (internal, `Task.php:L115-L124`).
- Callers visible in scope include `DashboardQueryService` and model-level queries. `TaskActivityFeedQuery`
  spells the same subquery explicitly (`TaskActivityFeedQuery.php:L23-L24`, `L43-L45`).

**Open Questions:**
- Whether every historical activity row preserves the task/project foreign-key relationship is a database
  invariant not established by this method; the schema/constraint tests are the source to consult.

---

## `TaskKeyQuery::displayKey` in app/Domain/Tasks/Queries/TaskKeyQuery.php (L7-L28)

**Purpose:** Converts an already-loaded persisted task into its stable `PROJECTKEY-number` display identifier.

**Inputs & Assumptions:**
- `$task` (`Task`): caller-supplied model. Trust: semi-trusted; visibility must be established by the
  caller/query before this function is invoked.
- Implicit: loaded `project` relation, matching `project_id`, nonblank `project.key`, and positive `number`
  (`TaskKeyQuery.php:L11-L24`).

**Outputs & Effects:**
- Returns uppercase project key plus task number (`TaskKeyQuery.php:L26-L28`), or throws `LogicException` for
  unsaved/incomplete identity (`L9-L24`). No writes.

**Block-by-Block:**

```php
// L9-L24
if (! $task->exists) { ... }
$project = $task->project;
if ($task->user_id === null || $task->project_id === null || $project === null
    || (string) $project->getKey() !== (string) $task->project_id
    || blank($project->key) || (int) $task->number < 1) { ... }
```
- **What:** Verifies the model identity needed to render a key.
- **Why here:** The formatter is called after task creation/detail/list queries and must not synthesize a key
  from an unrelated project relation.
- **Assumes:** `user_id` being non-null is part of a valid task identity, but this function does not compare it
  to a viewer.
- **Establishes:** The relation/key/number values are internally consistent before formatting.
- **Depended on by:** Task detail/list/board controllers and `CreateTask`.

**Cross-Function Dependencies:**
- Callers must supply a task whose visibility was established elsewhere; `TaskKeyQuery` itself has no
  `accessibleBy` call.

**Open Questions:**
- Nothing found in this function establishes that the supplied task is visible to the current viewer; the
  caller records carry that precondition.
