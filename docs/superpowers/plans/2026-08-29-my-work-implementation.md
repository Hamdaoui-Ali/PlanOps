# My Work Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task with review checkpoints. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the temporary `/my-work` screen with an owner-scoped, status-sectioned execution view supporting safe filters, due shortcuts, sorting, pagination, and quick task actions.

**Architecture:** Keep filtering in a typed `MyWorkFiltersRequest`, querying in one `MyWorkQuery`, and presentation in Blade components. The query returns all owned non-deleted tasks, eager-loads project/labels/direct-child aggregates, applies an allow-listed filter/sort map, and paginates at 50. Existing task status/priority actions remain the mutation source of truth; quick-action forms carry a signed-safe allow-listed return query.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Blade, Alpine/Vite already present, Pest/PHPUnit, optional Playwright/axe-core.

**Spec:** `docs/superpowers/specs/2026-08-29-my-work-design.md`

## Global Constraints

- Scope every task, project, label, filter option, and result by authenticated `user_id`.
- Exclude soft-deleted tasks; cancelled tasks are excluded from the default view and visible only through an explicit status filter.
- Default status sections are `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, and `NOT_STARTED`, in that order.
- Pagination is 50 tasks; default sort is recently updated.
- Supported filters are project, status, priority, label, due state, created date, and updated date.
- Supported due shortcuts are overdue, due today, due this week, and no due date.
- Supported sorts are recently updated, recently created, priority, due date, task key, and project; raw request values must never become SQL identifiers.
- Status and priority quick actions reuse existing domain actions and remain keyboard-operable, text-labelled, and color-independent.
- No saved filters, full-text search, drag-and-drop, client-side persistence, or project task-list screen in this tranche.

---

### Task 1: Define normalized filters and query contracts

**Files:**

- Create: `app/Http/Requests/MyWorkFiltersRequest.php`
- Create: `app/Domain/Tasks/Queries/MyWorkQuery.php`
- Create: `tests/Feature/MyWork/MyWorkFiltersTest.php`
- Create: `tests/Feature/MyWork/MyWorkSortingTest.php`

**Interfaces:**

- Consumes: `User`, `TaskStatus`, `TaskPriority`, `Project`, `Label`, `CarbonImmutable`, and the authenticated user's timezone.
- Produces: `MyWorkFiltersRequest::rules(): array`, `MyWorkFiltersRequest::filters(): array`, and `MyWorkQuery::paginate(User $owner, array $filters = [], int $perPage = 50): LengthAwarePaginator`.

- [ ] **Step 1: Write failing filter/query tests**

  Seed two users, two owned projects, labels, parent/child tasks, deleted tasks, and tasks in every status. Assert the default query returns only owned non-deleted tasks in `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, and `NOT_STARTED` sections; assert Backlog/Done/Canceled are available when `status` is explicitly selected. Cover project, priority, label, created date, updated date, and each due shortcut. Assert paginator size is 50 and foreign project/label ids never reveal another user's records.

- [ ] **Step 2: Add sorting tests before implementation**

  Assert each sort value produces the documented order with deterministic `id` tie-breaking: `updated`, `created`, `priority`, `due`, `task_key`, and `project`. Include null due dates last and reject an unknown sort value with validation. Assert the default is recently updated.

- [ ] **Step 3: Run the focused tests to confirm the red state**

  Run `php artisan test tests/Feature/MyWork`. Expected: PHP-unavailable failure in this environment or missing `MyWorkQuery`/request failures when PHP is available.

- [ ] **Step 4: Implement typed request normalization**

  Validate `project`, `label`, `status`, `priority`, `due`, `created_from`, `created_until`, `updated_from`, `updated_until`, and `sort` with date/enum/allow-list rules. Normalize empty strings to null, retain only documented keys, default `sort` to `updated`, and expose a `filters()` array that does not contain arbitrary request keys.

- [ ] **Step 5: Implement the owner-scoped query**

  Start from `Task::query()->ownedBy($owner)->with(['project', 'labels'])->withCount([...])`, exclude trashed rows, and apply filters only after ownership. Include all task levels for My Work, use `whereHas('project', ...)` for project ownership, constrain labels through the owned pivot relation, and use the owner's `preference->timezone` to calculate today/week boundaries from `CarbonImmutable`. Apply fixed sort maps: timestamps use columns plus `id`, priority uses a fixed `CASE`, due date uses nulls-last ordering, and task key/project sorts use project key/name plus task number/title and `id`.

- [ ] **Step 6: Run query tests to green and commit**

  Run `php artisan test tests/Feature/MyWork`. Expected: PASS when PHP is installed.

  ```powershell
  git add app/Http/Requests/MyWorkFiltersRequest.php app/Domain/Tasks/Queries/MyWorkQuery.php tests/Feature/MyWork
  git commit -m "feat: add My Work filters and query"
  ```

### Task 2: Add the My Work controller and route boundary

**Files:**

- Create: `app/Http/Controllers/MyWorkController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/MyWork/MyWorkFiltersTest.php`

**Interfaces:**

- Consumes: `MyWorkFiltersRequest`, `MyWorkQuery`, `Project`, `Label`, `TaskStatus`, `TaskPriority`, and existing owner-scoped bindings.
- Produces: `GET /my-work` named `my-work`; view data keys `tasks`, `filters`, `projects`, `labels`, `statuses`, `priorities`, and `focusStatuses`.

- [ ] **Step 1: Write failing controller tests**

  Assert authenticated users receive 200 from `/my-work`, guests are redirected by the existing auth middleware, default status sections are present in the expected order, project/label filter options contain only owned options, and query parameters are retained in pagination links.

- [ ] **Step 2: Implement `MyWorkController::index`**

  Inject `MyWorkQuery`, call `$request->filters()`, paginate with 50, load owned projects ordered by name and owned labels ordered by normalized/name, and pass explicit status/priority enums plus the four focus statuses to `pages.my-work.index`.

- [ ] **Step 3: Replace the temporary route**

  Remove `my-work` from the `Route::view` coming-soon loop and register `Route::get('/my-work', [MyWorkController::class, 'index'])->name('my-work')` inside the authenticated group. Preserve sidebar route naming and active-state behavior.

- [ ] **Step 4: Run controller tests and commit**

  Run `php artisan test tests/Feature/MyWork/MyWorkFiltersTest.php`. Expected: PASS when PHP is installed.

  ```powershell
  git add app/Http/Controllers/MyWorkController.php routes/web.php tests/Feature/MyWork/MyWorkFiltersTest.php
  git commit -m "feat: add My Work route boundary"
  ```

### Task 3: Build the status-sectioned My Work UI

**Files:**

- Create: `resources/views/pages/my-work/index.blade.php`
- Create: `resources/views/components/tasks/task-table.blade.php`
- Create: `resources/views/components/filters/task-filters.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/MyWork/MyWorkFiltersTest.php`

**Interfaces:**

- Consumes: paginator, normalized filters, focus statuses, project/label options, task enums, and named task mutation/detail routes.
- Produces: status-section layout, labelled filters, reset path, readable task rows, safe quick-action return context, pagination, and empty states.

- [ ] **Step 1: Write failing view assertions**

  Assert headings for `In Progress`, `In Review`, `Blocked`, and `Not Started`, section counts, task key/title/project/status/priority/due/labels/subtask/updated values, explicit labels for filters and quick actions, a visible reset link when filtered, pagination links, and exact empty-state copy `No tasks match these filters.` or `No tracked work yet.`.

- [ ] **Step 2: Implement the filter toolbar**

  Create a GET form with labelled project/status/priority/label/due/sort/date controls. Preserve only normalized values, expose due shortcuts, include `Reset filters` only when active, and keep the submitted URL bookmarkable. Use visible text labels and a submit button; no filter depends on JavaScript.

- [ ] **Step 3: Implement the reusable task table component**

  Render task key via `TaskKeyQuery`, title/project links via named routes, readable status and priority text, formatted due/updated dates, label names, direct-child summary, and a labelled status form plus labelled priority form. Append `return_context=my-work` plus the normalized `$filters` as the mutation form query string, e.g. `route('tasks.status', $task).'?'.http_build_query(['return_context' => 'my-work', ...$filters])`, so the current context is available without accepting an arbitrary redirect URL.

- [ ] **Step 4: Implement the sectioned page**

  Iterate `focusStatuses` first, group paginator tasks by status, render a section heading/count and `No tasks in this status.` for empty focus sections. If an explicit non-focus status is selected, render that status section. Render paginator links with the current filter query and an actionable global empty state with a Projects link for users with no tasks.

- [ ] **Step 5: Add responsive and accessible styling**

  Use the selected status-section layout: compact toolbar, readable section cards/table rows, visible focus rings, text statuses, comfortable controls, stacked mobile metadata, and no hover-only actions. Reuse PlanOps colors and density/theme classes; respect reduced motion.

- [ ] **Step 6: Run view/build checks and commit**

  Run `php artisan test tests/Feature/MyWork tests/Feature/Tasks/TaskMetadataTest.php` and `npm run build`. Expected: Laravel PASS when PHP is installed and Vite exit 0.

  ```powershell
  git add resources/views/pages/my-work/index.blade.php resources/views/components/tasks/task-table.blade.php resources/views/components/filters/task-filters.blade.php resources/css/app.css tests/Feature/MyWork/MyWorkFiltersTest.php
  git commit -m "feat: add status-sectioned My Work view"
  ```

### Task 4: Verify quick actions, context preservation, and accessibility

**Files:**

- Modify: `app/Http/Controllers/TaskController.php` if a safe return-query redirect is needed
- Modify: `resources/views/components/tasks/task-table.blade.php`
- Modify: `tests/Feature/MyWork/MyWorkSortingTest.php`
- Create: `tests/Browser/my-work-accessibility.spec.js` when a browser harness exists

**Interfaces:**

- Consumes: existing `ChangeTaskStatus`, `ChangeTaskPriority`, task policies, and normalized My Work filters.
- Produces: mutation redirects that preserve safe My Work context and browser-level keyboard/mobile checks when available.

- [ ] **Step 1: Write failing context-preservation tests**

  Submit a status and priority change with a return query containing valid filters and an unknown key. Assert the task changes through the existing actions, the redirect includes only allow-listed filters/sort values, and the unknown key is absent. Assert foreign task mutations remain unavailable.

- [ ] **Step 2: Implement safe redirect handling**

  Update `TaskController::changeStatus` and `changePriority` to detect the fixed `my-work` return marker in the request query, keep only the allow-listed filter/sort keys (`project`, `status`, `priority`, `label`, `due`, `created_from`, `created_until`, `updated_from`, `updated_until`, `sort`), and redirect with `to_route('my-work', $safeQuery)`. For all other requests preserve the existing task-detail redirect. Never accept or redirect to an arbitrary URL.

- [ ] **Step 3: Add browser checks when available**

  Tab through the filter toolbar, submit a filter, activate reset, open a task detail link, change status and priority without a mouse, verify visible focus and readable text status, run axe-core, and inspect a mobile viewport for clipping.

- [ ] **Step 4: Run final verification**

  ```powershell
  php artisan test tests/Feature/MyWork tests/Feature/Tasks tests/Unit/Domain/Tasks
  npm run build
  git diff --check
  git status --short --branch
  ```

  If PHP is unavailable, explicitly report that Laravel tests were not executed. If no browser harness exists, explicitly report that browser/axe checks were not executed.

- [ ] **Step 5: Commit final verification changes**

  ```powershell
  git add app/Http/Controllers/TaskController.php resources/views/components/tasks/task-table.blade.php tests/Feature/MyWork tests/Browser/my-work-accessibility.spec.js
  git commit -m "test: verify My Work interactions"
  ```

## Final handoff

- Do not push unless explicitly requested.
- Report every commit, the exact Vite result, Laravel test availability/result, and browser/axe availability/result.
