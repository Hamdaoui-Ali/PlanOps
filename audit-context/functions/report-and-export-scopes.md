## `ExportQueryService::projects` in app/Domain/Export/Queries/ExportQueryService.php (L14-L20)

**Purpose:** Streams projects the viewer may include in a complete project export with eligible/completed
top-level task counts.

**Inputs & Assumptions:**
- `$owner` (`User`): authenticated export caller. Trust: trusted controller/FormRequest subject.
- Implicit: `Project::exportableBy` is the full project boundary; task counts use non-cancelled and DONE
  status strings (`ExportQueryService.php:L16-L19`).

**Outputs & Effects:**
- Returns an ID-ordered `LazyCollection`; reads projects and aggregate task counts, no writes
  (`ExportQueryService.php:L14-L20`).

**Block-by-Block:**

```php
// L16-L19
return Project::query()->exportableBy($owner)->withCount([...])->orderBy('id')->lazyById(100);
```
- **What:** Applies report/export scope before loading the stream.
- **Why here:** Export is a global endpoint with no project parameter.
- **Assumes:** `exportableBy` accurately represents complete-report permission.
- **Establishes:** Every emitted project row was selected through the export scope.
- **Depended on by:** `ExportController::projects`.

**Cross-Function Dependencies:**
- Callee `Project::scopeExportableBy` (`Project.php:L106-L126`).
- Caller `ExportController::projects` (`ExportController.php:L11-L22`).
- Shared task status semantics with `ProjectIndexQuery` and `Project::progressCounts`.

**Open Questions:**
- The count closures do not add `withTrashed` or explicit project filters beyond the relation; the relation
  supplies `project_id`, and default Task SoftDeletes behavior is assumed (`Task.php:L24-L26`).

---

## `ExportQueryService::tasks` in app/Domain/Export/Queries/ExportQueryService.php (L22-L28)

**Purpose:** Streams tasks whose related project satisfies the export scope.

**Inputs & Assumptions:**
- `$owner` (`User`): authenticated export caller. Trust: trusted controller/FormRequest subject.
- Implicit: each task's `project` relation is present and the project scope is sufficient to bind task rows
  to exportable projects (`ExportQueryService.php:L24-L27`).

**Outputs & Effects:**
- Returns an ID-ordered lazy stream with project, parent, and label relations (`ExportQueryService.php:L22-L28`).
  No writes.

**Block-by-Block:**

```php
// L24-L27
return Task::query()->whereHas('project', fn (Builder $projects): Builder => $projects->exportableBy($owner))
    ->with([...])->orderBy('id')->lazyById(100);
```
- **What:** Constrains task rows through their project's exportability.
- **Why here:** The endpoint does not accept a task/project selector; the relation predicate is the boundary.
- **Assumes:** Project/task foreign-key relationship is intact and task rows without a matching project are
  not part of the export contract.
- **Establishes:** Each yielded task has an exportable project relation under the query predicate.
- **Depended on by:** `ExportController::tasks`.

**Cross-Function Dependencies:**
- Callee `Project::scopeExportableBy` (`Project.php:L106-L126`).
- Caller `ExportController::tasks` (`ExportController.php:L24-L34`).
- Eager-loaded `labels` are selected without a separate label scope; the task/project export boundary is the
  implicit ownership context.

**Open Questions:**
- Nothing in this method separately constrains eager-loaded labels to the exportable project; the intended
  project-label relation behavior is not established here.

---

## `ExportQueryService::activity` in app/Domain/Export/Queries/ExportQueryService.php (L30-L36)

**Purpose:** Streams activity rows whose related project satisfies the export scope.

**Inputs & Assumptions:**
- `$owner` (`User`): authenticated export caller. Trust: trusted controller/FormRequest subject.
- Implicit: activity `project_id` relation is authoritative for project-scoped export and task relation is
  available for row formatting (`ExportQueryService.php:L32-L35`).

**Outputs & Effects:**
- Returns an ID-ordered lazy activity stream with limited project/task columns (`ExportQueryService.php:L30-L36`).
  No writes.

**Cross-Function Dependencies:**
- Callee `Project::scopeExportableBy` through `whereHas('project')` (`ExportQueryService.php:L32-L33`).
- Caller `ExportController::activity` (`ExportController.php:L36-L62`).
- Formatter `ExportController::activityRow` constructs task key from the loaded project/task pair
  (`ExportController.php:L64-L67`).

**Open Questions:**
- The query does not independently filter trashed tasks; the selected columns include `deleted_at` and the
  formatter does not reject it (`ExportQueryService.php:L32-L34`, `ExportController.php:L64-L67`). The intended
  historical-export contract remains a question for the hunting/verification phase.

---

## `ExportRequest::authorize` in app/Http/Requests/ExportRequest.php (L11-L14)

**Purpose:** Gates all export endpoints before the controller creates a stream.

**Inputs & Assumptions:**
- `$this->user()` (`User|null`): framework-authenticated subject. Trust: semi-trusted request context.
- Implicit: `ProjectPolicy::exportAny` is registered for `Project` (`AppServiceProvider.php:L27-L32`).

**Outputs & Effects:**
- Returns the result of `can('exportAny', Project::class)`, or false for no user
  (`ExportRequest.php:L11-L14`). No writes.

**Cross-Function Dependencies:**
- Callee `ProjectPolicy::exportAny` (`ProjectPolicy.php:L61-L64`) and `Project::exportableBy`
  (`Project.php:L106-L126`).
- Callers: `ExportController::projects`, `tasks`, and `activity` (`ExportController.php:L11-L62`).

**Open Questions:**
- This request-level ability is global; per-row scope is delegated to the three query methods and is not
  established by the FormRequest alone.
