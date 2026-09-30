## `Route::bind('project', ...)` closure in routes/web.php (L44-L46)

**Purpose:** Resolves a route `{project}` value to a project visible to the current request user. A missing
or out-of-scope row becomes the framework's not-found result through `findOrFail`.

**Inputs & Assumptions:**
- `$value` (string): route parameter. Trust: **untrusted**.
- Implicit: `request()->user()`; the closure is registered inside the authenticated route group
  (`routes/web.php:L36-L46`). The route group establishes authentication; nothing in the closure checks
  `deactivated_at` (`routes/web.php:L36-L46`, `bootstrap/app.php:L13-L15`).

**Outputs & Effects:**
- Returns a `Project` selected by `Project::accessibleBy` or throws a not-found exception
  (`routes/web.php:L44-L46`). No state writes or external calls.

**Block-by-Block:**

```php
// L44-L46
Route::bind('project', function (string $value): Project {
    return Project::query()->accessibleBy(request()->user())->findOrFail($value);
});
```
- **What:** Applies the viewer scope before resolving the supplied identifier.
- **Why here:** Every authenticated route using `{project}` receives the scoped model before controller
  execution.
- **Assumes:** `request()->user()` is non-null and is the intended viewer; the route group's `auth`
  middleware is the source of that guarantee (`routes/web.php:L36-L46`).
- **Establishes:** The returned project satisfies `Project::accessibleBy`, whose predicate is recorded in
  `project-access-scopes.md`.
- **Depended on by:** Project controllers and nested routes in `routes/web.php:L52-L99`.

**Cross-Function Dependencies:**
- Callee `Project::scopeAccessibleBy` (internal, `app/Domain/Projects/Models/Project.php:L92-L104`):
  supplies owner/legacy-owner/active-membership visibility.
- Callers: Laravel route model binding for every `{project}` parameter in the authenticated group
  (`routes/web.php:L52-L99`).
- Shared state: `projects` and `project_memberships` through the scope.

**Open Questions:**
- Whether the installed authentication middleware rejects a `User` whose `deactivated_at` is non-null is
  not established by the scoped route/bootstrap files; nothing found in `bootstrap/app.php:L13-L15`.

---

## `Route::bind('task', ...)` closure in routes/web.php (L48-L50)

**Purpose:** Resolves a route `{task}` value to a task visible to the current request user.

**Inputs & Assumptions:**
- `$value` (string): route parameter. Trust: **untrusted**.
- Implicit: authenticated `request()->user()` from the surrounding route group
  (`routes/web.php:L36-L50`). Deactivation handling is not established in this closure.

**Outputs & Effects:**
- Returns the first matching accessible `Task`, or a not-found result through `findOrFail`
  (`routes/web.php:L48-L50`). No state writes.

**Block-by-Block:**

```php
// L48-L50
Route::bind('task', function (string $value): Task {
    return Task::query()->accessibleBy(request()->user())->findOrFail($value);
});
```
- **What:** Applies task visibility before consuming the route identifier.
- **Why here:** Controllers that type-hint `Task $task` receive the viewer-scoped record at the route
  boundary.
- **Assumes:** `Task::accessibleBy` correctly joins project visibility and its legacy branch; see
  `task-and-related-scopes.md`.
- **Establishes:** The bound task is in the task ID set returned by the viewer scope.
- **Depended on by:** Task routes in `routes/web.php:L78-L93`, including the board status route with
  `scopeBindings()` at `L78-L80`.

**Cross-Function Dependencies:**
- Callee `Task::scopeAccessibleBy` (internal, `app/Domain/Tasks/Models/Task.php:L115-L124`): checks
  project accessibility or the no-membership legacy-owner branch.
- Callers: Laravel binding for task routes and controller/FormRequest parameters.
- Shared state: `tasks`, `projects`, and `project_memberships`; normal `Task` queries exclude soft-deleted
  rows because the model uses `SoftDeletes` (`Task.php:L24-L26`).

**Open Questions:**
- Whether routes needing historical soft-deleted tasks intentionally bypass this binder is unclear; the
  visible task routes all use the normal binding and no route-level `withTrashed` option was found
  (`routes/web.php:L78-L93`).
