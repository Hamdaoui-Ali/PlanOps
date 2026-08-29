# Project Board Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task with review checkpoints. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an owner-scoped, accessible project board with fixed status columns, non-drag status movement, and transactional same-column reordering.

**Architecture:** Add a read-only `ProjectBoardQuery` that returns top-level tasks grouped by `TaskStatus`, a transactional `ReorderTasks` action for validated position updates, and a thin controller/request boundary. Render the board with Blade and the existing Alpine/Vite stack; use ordinary forms for the complete keyboard/mobile path and keep drag enhancement out of the critical path.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Blade, Alpine.js, Tailwind/CSS, Vite, Pest/PHPUnit, optional Playwright/axe-core browser checks.

**Spec:** `docs/superpowers/specs/2026-08-29-project-board-design.md`

## Global Constraints

- Scope every project and task read/write by authenticated `user_id`; foreign resources may resolve as 404.
- Board cards represent non-deleted top-level tasks only.
- Default columns are `BACKLOG`, `NOT_STARTED`, `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, and `DONE`; `CANCELLED` is opt-in through a labelled filter.
- Status changes reuse `ChangeTaskStatus`; do not duplicate lifecycle timestamp or activity logic.
- Reordering accepts only ids from the same owner, project, top-level scope, and requested status, then writes contiguous positions in one transaction.
- Every card has a visible task link and labelled non-drag status control; no essential action is drag-only, hover-only, or icon-only.
- Subtasks are summarized on cards but never become board cards and never affect project progress.
- Preserve readable text status, visible focus, responsive layouts, reduced-motion behavior, and existing PlanOps visual language.

---

### Task 1: Add the board read query and data contract

**Files:**

- Create: `app/Domain/Tasks/Queries/ProjectBoardQuery.php`
- Create: `tests/Unit/Domain/Tasks/ProjectBoardQueryTest.php`
- Create: `tests/Feature/Projects/ProjectBoardTest.php`

**Interfaces:**

- Consumes: `Project`, `Task`, `TaskStatus`, `User`, `TaskKeyQuery`, and the existing `ownedBy` scopes.
- Produces: `ProjectBoardQuery::for(User|int $owner, Project $project, bool $includeCancelled = false): array` returning `array<string, Collection<int, Task>>`, keyed by status value.

- [ ] **Step 1: Write failing query tests**

  Seed one owned project with a task in every status, a child task, a soft-deleted task, and a foreign project/task. Assert the default result has exactly the six non-cancelled keys, contains only owned top-level tasks, and orders each group by `position` then `number`. Assert each task has `project`, `labels`, `children_count`, `eligible_children_count`, and `completed_children_count` loaded.

- [ ] **Step 2: Run the focused tests**

  Run `php artisan test tests/Unit/Domain/Tasks/ProjectBoardQueryTest.php tests/Feature/Projects/ProjectBoardTest.php`. Expected: FAIL because `ProjectBoardQuery` and the board route do not exist.

- [ ] **Step 3: Implement the bounded query**

  Use a project ownership check followed by one top-level task query. Build the status list from `TaskStatus::cases()`, remove `CANCELLED` unless `$includeCancelled` is true, eager-load `project`, `labels`, and direct `children`, add the two child aggregates using `TaskStatus::CANCELLED->value` and `TaskStatus::DONE->value`, then group the collection by `$task->status->value` and return an entry for every requested column, including empty collections.

- [ ] **Step 4: Run query tests to green**

  Run `php artisan test tests/Unit/Domain/Tasks/ProjectBoardQueryTest.php`. Expected: PASS, including ownership, soft-delete, top-level, grouping, eager-loading, and cancelled-filter assertions.

- [ ] **Step 5: Commit the read boundary**

  ```powershell
  git add app/Domain/Tasks/Queries/ProjectBoardQuery.php tests/Unit/Domain/Tasks/ProjectBoardQueryTest.php tests/Feature/Projects/ProjectBoardTest.php
  git commit -m "feat: add project board query"
  ```

### Task 2: Implement transactional board reordering

**Files:**

- Create: `app/Domain/Tasks/Actions/ReorderTasks.php`
- Create: `tests/Unit/Domain/Tasks/ReorderTasksTest.php`

**Interfaces:**

- Consumes: `User`, `Project`, `TaskStatus`, and an ordered list of task ids.
- Produces: `ReorderTasks::handle(User $owner, Project $project, TaskStatus $status, array $orderedTaskIds): void`.

- [ ] **Step 1: Write failing action tests**

  Cover contiguous positions for a valid order, rejection of a foreign id, rejection of an id from another project, rejection of a child task, rejection of a deleted task, rejection of a task whose current status differs, and rollback when validation fails. Assert invalid input leaves every original position unchanged.

- [ ] **Step 2: Run the focused action tests**

  Run `php artisan test tests/Unit/Domain/Tasks/ReorderTasksTest.php`. Expected: FAIL because the action does not exist.

- [ ] **Step 3: Implement validation and transaction boundaries**

  Start a `DB::transaction`, lock the project-scoped task rows with `whereIn('id', $orderedTaskIds)->lockForUpdate()`, verify the count and each invariant before saving anything, reject duplicate ids by comparing `array_unique` count, then assign positions `0..n-1` with `forceFill(['position' => $position])->save()`. Throw a validation exception containing `ordered_task_ids` for malformed or mismatched input.

- [ ] **Step 4: Run action tests to green**

  Run `php artisan test tests/Unit/Domain/Tasks/ReorderTasksTest.php`. Expected: PASS with no partial mutation on every invalid case.

- [ ] **Step 5: Commit the transaction boundary**

  ```powershell
  git add app/Domain/Tasks/Actions/ReorderTasks.php tests/Unit/Domain/Tasks/ReorderTasksTest.php
  git commit -m "feat: add transactional board reorder"
  ```

### Task 3: Add HTTP controller, validation, and routes

**Files:**

- Create: `app/Http/Controllers/ProjectBoardController.php`
- Create: `app/Http/Requests/ReorderTasksRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `tests/Feature/Projects/ProjectBoardTest.php`

**Interfaces:**

- Consumes: `ProjectBoardQuery`, `ReorderTasks`, existing `ChangeTaskStatus`, `ChangeTaskStatusRequest`, and owner-scoped route bindings.
- Produces: `GET /projects/{project}/board` named `projects.board`; `POST /projects/{project}/board/reorder` named `projects.board.reorder`; `POST /projects/{project}/board/tasks/{task}/status` named `projects.board.tasks.status`; all board mutations redirect to `projects.board`.

- [ ] **Step 1: Write failing HTTP tests**

  Assert an owner can load the board, a foreign project returns 404, `include_cancelled=1` reveals cancelled tasks, a valid reorder redirects back to the board and persists positions, malformed reorder input returns validation errors, and a board status submission changes the task and redirects to the board without bypassing activity recording.

- [ ] **Step 2: Run the focused feature tests**

  Run `php artisan test tests/Feature/Projects/ProjectBoardTest.php`. Expected: FAIL because the board controller, request, and routes are absent.

- [ ] **Step 3: Implement the request and controller**

  Validate `status` with `Rule::in(array_column(TaskStatus::cases(), 'value'))` and `ordered_task_ids` as a required array of distinct existing integer ids. The board controller passes the authenticated owner, project, query result, status cases, and `includeCancelled` flag to the view. The reorder method converts the validated status to `TaskStatus::from`, calls `ReorderTasks`, and returns `to_route('projects.board', $project)->with('status', 'Board order updated.')`.

- [ ] **Step 4: Add the board-specific status endpoint**

  Add `ProjectBoardController::changeStatus(ChangeTaskStatusRequest $request, Project $project, Task $task, ChangeTaskStatus $changeStatus): RedirectResponse`. Verify the task belongs to the bound project before calling `ChangeTaskStatus`, then redirect to `projects.board`. Keep `TaskController::changeStatus` unchanged for task detail and ensure both endpoints use the same domain action.

- [ ] **Step 5: Register route order safely**

  Add project board routes after the owner-scoped project/task bindings and before `/projects/{project}` fallback routes. Register `POST /projects/{project}/board/reorder` and `POST /projects/{project}/board/tasks/{task}/status` before `GET /projects/{project}/board`; retain the existing owner-scoped bindings and ensure the controller rejects a task whose `project_id` differs from the bound project.

- [ ] **Step 6: Run HTTP tests to green and commit**

  Run `php artisan test tests/Feature/Projects/ProjectBoardTest.php tests/Feature/Tasks/ChangeTaskStatusTest.php`. Expected: PASS.

  ```powershell
  git add app/Http/Controllers/ProjectBoardController.php app/Http/Requests/ReorderTasksRequest.php app/Http/Controllers/TaskController.php routes/web.php tests/Feature/Projects/ProjectBoardTest.php
  git commit -m "feat: expose project board routes"
  ```

### Task 4: Build the responsive accessible board UI

**Files:**

- Create: `resources/views/pages/projects/board.blade.php`
- Create: `resources/views/components/tasks/task-card.blade.php`
- Modify: `resources/views/components/navigation/sidebar.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/Projects/ProjectBoardTest.php`

**Interfaces:**

- Consumes: board columns, `TaskStatus::cases()`, project, task card fields, named task/project routes, and controller flash/error data.
- Produces: six-column desktop/tablet board, stacked mobile layout, labelled cancelled filter, keyboard-operable status forms, visible focus, and accessible empty states.

- [ ] **Step 1: Write failing markup assertions**

  Assert the response contains each default column label, a task key/title/priority/due date, a task-detail link, `Move to` labels, `No tasks in this status.` for empty columns, and the cancelled filter. Assert the board navigation link is present for an owned project and absent for foreign data.

- [ ] **Step 2: Implement the reusable task card**

  Render the task key with `TaskKeyQuery`, title link using `route('tasks.show', $task)`, readable status and priority text, optional due date, label chips, and direct child progress. Add a labelled `select` listing all statuses plus a submit button with visible text. Add a hidden accessible description only for supplementary context; never make the status meaning color-only.

- [ ] **Step 3: Implement the board view**

  Render the project header with a Back to overview link, the flash status, a GET checkbox/select for cancelled tasks, and a `<section>` per status. For each non-empty column, render cards in query order plus visible `Move up`/`Move down` buttons that submit the complete ordered id list to `projects.board.reorder`; disable or omit the button at the relevant edge. If no card exists, render `No tasks in this status.`; if the entire board is empty, render a task-creation link.

- [ ] **Step 4: Add responsive and focus styling**

  Add board grid styles for six columns with horizontal scrolling at tablet widths, stack columns on small screens, card spacing, visible focus rings, readable metadata, and a reduced-motion media query. Use existing PlanOps tokens and avoid hover-only affordances.

- [ ] **Step 5: Run view/build checks and commit**

  Run `php artisan test tests/Feature/Projects/ProjectBoardTest.php` and `npm run build`. Expected: PASS and a successful Vite production build.

  ```powershell
  git add resources/views/pages/projects/board.blade.php resources/views/components/tasks/task-card.blade.php resources/views/components/navigation/sidebar.blade.php resources/css/app.css tests/Feature/Projects/ProjectBoardTest.php
  git commit -m "feat: add accessible project board UI"
  ```

### Task 5: Complete regression and accessibility verification

**Files:**

- Modify: `tests/Feature/Projects/ProjectBoardTest.php`
- Create: `tests/Browser/project-board-accessibility.spec.js` when the browser harness exists
- Modify: `docs/ui/screen-spec.md` only if the implemented route or copy differs from the existing contract

**Interfaces:**

- Consumes: all board query, action, route, and view contracts from Tasks 1–4.
- Produces: verified ownership, status movement, ordering, cancelled filtering, responsive behavior, and keyboard accessibility.

- [ ] **Step 1: Add regression cases**

  Assert child status/priority/due-date changes do not create board cards, deleted tasks disappear, cancelled tasks appear only when requested, project progress remains top-level-only, and invalid reorder requests do not change positions.

- [ ] **Step 2: Add browser checks when available**

  Open an owned project board, tab to a card's status select, move a task without dragging, assert the visible text status and focus ring, verify keyboard access to task detail, and run axe-core against the board. At a mobile viewport, assert columns remain readable and the status control is usable.

- [ ] **Step 3: Run complete verification**

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/ProjectBoardQueryTest.php tests/Unit/Domain/Tasks/ReorderTasksTest.php tests/Feature/Projects/ProjectBoardTest.php tests/Feature/Projects/ProjectOverviewTest.php tests/Feature/Tasks/ChangeTaskStatusTest.php
  npm run build
  git diff --check
  git status --short --branch
  ```

  Expected: all available Laravel tests pass, the frontend build succeeds, diff check is clean, and only intentional plan/spec changes remain before the final commit.

- [ ] **Step 4: Commit verification updates**

  ```powershell
  git add tests/Feature/Projects/ProjectBoardTest.php tests/Browser/project-board-accessibility.spec.js docs/ui/screen-spec.md
  git commit -m "test: verify project board accessibility"
  ```

## Final handoff

- Do not push commits unless explicitly requested.
- Report the exact Laravel test command/result, Vite build result, and whether browser/axe checks were available.
- If PHP is unavailable, state that Laravel tests were not executed; do not claim the board is fully verified.
