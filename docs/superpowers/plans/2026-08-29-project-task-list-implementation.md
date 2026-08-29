# Project Task List Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task with review checkpoints. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an owner-scoped `/projects/{project}/tasks` list with hierarchy-aware rows, local filters/sorts, pagination, and quick actions that return to the project context.

**Architecture:** Create a project-bound `ProjectTaskListQuery` and a small `ProjectTaskListFiltersRequest` that reuse the safe filter vocabulary from `My Work` without cross-project inputs. Render a dedicated page and reusable project task table; extend task mutation redirects with a fixed `project-tasks` context marker and allow-listed query values.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Blade, Alpine/Vite already present, Pest/PHPUnit, optional Playwright/axe-core.

**Spec:** `docs/superpowers/specs/2026-08-29-project-task-list-design.md`

## Global Constraints

- Scope every task, project, label, filter option, and result by authenticated `user_id` and the bound project.
- Include non-deleted top-level tasks and direct subtasks; never recursively load grandchildren.
- Cancelled tasks appear only when the explicit status filter selects `CANCELLED`.
- Paginate at 50 tasks and default to recently updated ordering.
- Supported filters are status, priority, label, and due state; due values are `overdue`, `today`, `this_week`, and `no_due_date`.
- Supported sorts are `updated`, `created`, `priority`, `due`, and `task_key`; request values never become raw SQL identifiers.
- Status and priority quick actions reuse existing domain actions and remain text-labelled and keyboard-operable.
- Project progress remains based on completed eligible top-level tasks only.
- No saved filters, global search, new drag-and-drop behavior, or automatic parent/child status synchronization.

---

### Task 1: Add project-scoped filters and task-list query

**Files:**

- Create: `app/Http/Requests/ProjectTaskListFiltersRequest.php`
- Create: `app/Domain/Tasks/Queries/ProjectTaskListQuery.php`
- Create: `tests/Feature/Tasks/ProjectTaskListTest.php`

**Interfaces:**

- Consumes: `User`, owner-scoped `Project`, `Task`, `TaskStatus`, `TaskPriority`, `CarbonImmutable`, and labels.
- Produces: `ProjectTaskListFiltersRequest::filters(): array` and `ProjectTaskListQuery::paginate(User $owner, Project $project, array $filters = [], int $perPage = 50): LengthAwarePaginator`.

- [ ] **Step 1: Write failing query tests**

  Seed an owned project with two top-level tasks, direct subtasks, a deleted task, cancelled task, labels, and a foreign project. Assert the query includes visible parents and direct children, excludes deleted rows and foreign rows, returns project/parent/labels plus child counts, and limits results to the bound project. Assert default ordering is updated descending with id tie-breaking and page size is capped at 50.

- [ ] **Step 2: Add failing filter and sort assertions**

  Cover status, priority, label, overdue/today/this_week/no_due_date, created ordering, priority ordering, due nulls-last, and task-key ordering. Assert an unsupported filter or sort fails request validation before query execution.

- [ ] **Step 3: Run the focused tests to confirm the red state**

  Run `php artisan test tests/Feature/Tasks/ProjectTaskListTest.php`. Expected: PHP-unavailable failure here or missing request/query/route failures when PHP is available.

- [ ] **Step 4: Implement the project-list request**

  Normalize empty values to null and validate only `status`, `priority`, `label`, `due`, and `sort` using the existing enum and allow-list values. Return only normalized non-null keys and default sort behavior to `updated` when missing.

- [ ] **Step 5: Implement the bounded query**

  Start from `Task::query()->ownedBy($owner)->where('project_id', $project->id)`, eager-load `project`, `parent`, and `labels`, add `children_count`, `eligible_children_count`, and `completed_children_count`, exclude trashed rows, apply filters, then apply fixed sort maps and `paginate(50)->withQueryString()`. Use the owner's timezone for due shortcuts and never accept a project id from the request.

- [ ] **Step 6: Run tests/build checks and commit**

  Run `php artisan test tests/Feature/Tasks/ProjectTaskListTest.php` and `npm run build`. Expected: Laravel PASS when PHP is installed and Vite exit 0.

  ```powershell
  git add app/Http/Requests/ProjectTaskListFiltersRequest.php app/Domain/Tasks/Queries/ProjectTaskListQuery.php tests/Feature/Tasks/ProjectTaskListTest.php
  git commit -m "feat: add project task list query"
  ```

### Task 2: Add controller and project task-list route

**Files:**

- Create: `app/Http/Controllers/ProjectTaskListController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Tasks/ProjectTaskListTest.php`

**Interfaces:**

- Consumes: `ProjectTaskListFiltersRequest`, `ProjectTaskListQuery`, bound `Project`, `TaskKeyQuery`, labels, and task enums.
- Produces: `GET /projects/{project}/tasks` named `projects.tasks.index`; view data `project`, `tasks`, `filters`, `labels`, `statuses`, `priorities`, and `keys`.

- [ ] **Step 1: Write failing HTTP boundary tests**

  Assert an owner receives 200, a foreign project returns 404, the response includes only labels owned by the current user, query parameters remain on pagination links, and the list route does not collide with task creation or project overview routes.

- [ ] **Step 2: Implement the controller**

  Call `$request->filters()`, paginate the bound project at 50, load owned labels ordered by normalized name, pass `TaskStatus::cases()`, `TaskPriority::cases()`, and `TaskKeyQuery` to `pages.projects.tasks`.

- [ ] **Step 3: Register the route safely**

  Add `Route::get('/projects/{project}/tasks', [ProjectTaskListController::class, 'index'])->name('projects.tasks.index')` inside the authenticated group after the owner-scoped project binding and before the generic project overview route. Keep `/projects/{project}/tasks/create` and task creation routes intact.

- [ ] **Step 4: Run the boundary tests and commit**

  Run `php artisan test tests/Feature/Tasks/ProjectTaskListTest.php`. Expected: PASS when PHP is installed.

  ```powershell
  git add app/Http/Controllers/ProjectTaskListController.php routes/web.php tests/Feature/Tasks/ProjectTaskListTest.php
  git commit -m "feat: add project task list route"
  ```

### Task 3: Build the hierarchy-aware project list UI

**Files:**

- Create: `resources/views/pages/projects/tasks.blade.php`
- Create: `resources/views/components/tasks/project-task-table.blade.php`
- Create: `resources/views/components/filters/project-task-filters.blade.php`
- Modify: `resources/views/pages/projects/show.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/Tasks/ProjectTaskListTest.php`

**Interfaces:**

- Consumes: project-list paginator, direct parent relation, task key service, filter options, and named task/project routes.
- Produces: project header, overview/board/list navigation, labelled filters, hierarchy-aware rows, quick actions, empty states, and responsive styles.

- [ ] **Step 1: Write failing view assertions**

  Assert the response contains project name/key, `Back to overview`, `Open board`, `New task`, filter labels, task/subtask keys and titles, readable status/priority/due/updated values, labels, subtask summary, task-detail links, and empty-state copy.

- [ ] **Step 2: Implement project task filters**

  Create a GET form for status, priority, label, due, and sort. Include visible `Apply filters` and `Reset filters` controls, preserve the current project route, and avoid JavaScript-only filtering.

- [ ] **Step 3: Implement the project task table**

  Render task key/title, project-local hierarchy indentation for rows with a parent, status, priority, due date, labels, direct-subtask summary, and updated timestamp. Provide visible links to `tasks.show`; render labelled status/priority quick forms whose query string includes `return_context=project-tasks`, the bound project id is not user-editable, and normalized filter values.

- [ ] **Step 4: Implement the page and navigation**

  Render the project header and local navigation. Show `This project has no tracked work yet.` with a `New task` link when the project has no rows; show `No tasks match these filters.` with `Reset filters` for filtered emptiness. Render pagination with current query parameters.

- [ ] **Step 5: Add responsive and accessible styling**

  Reuse My Work table/filter patterns, add parent indentation and a subtle rule for direct subtasks, keep visible focus, readable text statuses, comfortable controls, mobile stacking, and reduced-motion compatibility.

- [ ] **Step 6: Run view/build checks and commit**

  Run `php artisan test tests/Feature/Tasks/ProjectTaskListTest.php` and `npm run build`. Expected: Laravel PASS when PHP is installed and Vite exit 0.

  ```powershell
  git add resources/views/pages/projects/tasks.blade.php resources/views/components/tasks/project-task-table.blade.php resources/views/components/filters/project-task-filters.blade.php resources/views/pages/projects/show.blade.php resources/css/app.css tests/Feature/Tasks/ProjectTaskListTest.php
  git commit -m "feat: add project task list UI"
  ```

### Task 4: Verify quick-action context and accessibility

**Files:**

- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `tests/Feature/Tasks/ProjectTaskListTest.php`
- Create: `tests/Browser/project-task-list-accessibility.spec.js` when a browser harness exists

**Interfaces:**

- Consumes: existing `ChangeTaskStatus`, `ChangeTaskPriority`, task policies, and project-list normalized filters.
- Produces: safe redirects to `projects.tasks.index` and browser-level keyboard/mobile checks when available.

- [ ] **Step 1: Write failing redirect tests**

  Submit status and priority changes with `return_context=project-tasks`, valid project/filter query values, and an unknown parameter. Assert the existing actions mutate only the selected task, redirects return to the bound project task list, and the unknown parameter is removed. Assert foreign task/project requests remain unavailable.

- [ ] **Step 2: Implement fixed project-list redirect handling**

  Add a `safeProjectTaskListQuery(Request $request): array` helper that accepts only `status`, `priority`, `label`, `due`, and `sort` values from their fixed allow-lists. When `return_context` equals `project-tasks`, redirect with `to_route('projects.tasks.index', ['project' => $task->project_id, ...$safeQuery])` using the task's own project id; otherwise preserve the existing task-detail redirect.

- [ ] **Step 3: Add browser checks when available**

  Tab through filters, apply and reset filters, open a parent and subtask detail, change status/priority without a mouse, verify visible focus and readable text, run axe-core, and inspect mobile row stacking.

- [ ] **Step 4: Run final verification**

  ```powershell
  php artisan test tests/Feature/Tasks/ProjectTaskListTest.php tests/Feature/Tasks tests/Feature/Projects/ProjectOverviewTest.php
  npm run build
  git diff --check
  git status --short --branch
  ```

  If PHP is unavailable, explicitly report that Laravel tests were not executed. If no browser harness exists, explicitly report that browser/axe checks were not executed.

- [ ] **Step 5: Commit verification changes**

  ```powershell
  git add app/Http/Controllers/TaskController.php tests/Feature/Tasks/ProjectTaskListTest.php tests/Browser/project-task-list-accessibility.spec.js
  git commit -m "test: verify project task list interactions"
  ```

## Final handoff

- Do not push unless explicitly requested.
- Report each commit, the exact Vite result, Laravel test availability/result, and browser/axe availability/result.
