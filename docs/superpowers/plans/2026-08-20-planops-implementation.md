# PlanOps Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build PlanOps as a personal, user-scoped Laravel work-operations application that records explicit project/task state changes and derives trustworthy boards, activity feeds, dashboards, and analytics from those stored facts.

**Architecture:** Use one Laravel 13 modular monolith with PHP 8.3, PostgreSQL, session authentication, and server-rendered Blade/Livewire interactions. Keep business mutations in explicit domain Actions, reads in Query Services, and all domain facts in Projects, Tasks, Labels, UserPreferences, and append-only TaskActivities; the presentation adapter must not change those semantics.

**Tech Stack:** Laravel 13; PHP 8.3; PostgreSQL; Laravel session authentication; Blade, Livewire, Alpine, Tailwind CSS, and Vite for the web UI; Pest/PHPUnit for unit and feature tests; Playwright with axe-core checks for browser and accessibility coverage; no required queue worker, scheduler, WebSocket service, or external search service.

**Spec:** [`planops-complete-spec.md`](../../../planops-complete-spec.md)

## Global Constraints

- Use the primary baseline exactly: **Laravel 13 / PHP 8.3**.
- Scope every user-owned query, relationship, action, policy, and search by `user_id`; cross-user resources may return `404`.
- Keep the persistent core to `users`, `user_preferences`, `projects`, `tasks`, `labels`, `task_label`, and `task_activities`.
- Use the fixed task statuses `BACKLOG`, `NOT_STARTED`, `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, `DONE`, and `CANCELLED`; do not build a custom workflow editor.
- Keep project status manual: `PLANNED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, and `CANCELLED`; task completion never changes it automatically.
- Project progress counts only non-cancelled top-level tasks: `completed top-level tasks / eligible top-level tasks`; zero eligible tasks displays `0%` with `No active scope`.
- Store real timestamps in UTC-capable columns, store deadlines as date-only `DATE` values, and resolve Today/Week/Month/Year/custom boundaries in the user’s IANA timezone.
- Allocate project-local task numbers by locking the project row inside the create-task transaction; numbers are never reused and task keys are never changed in normal v1 flows.
- Record meaningful mutations through append-only `TaskActivity`; do not record page views, filters, cache refreshes, autosave keystrokes, or inferred computer activity.
- Every status transition is user-declared; elapsed lead/cycle/time-in-status values are workflow elapsed time, never hours worked or productivity scores.
- Keep task hierarchy to `Project → Task → Subtask`; subtasks cannot contain subtasks, and parent/child project ownership must match.
- Use soft deletion for tasks; exclude soft-deleted tasks from active views and standard progress/analytics while retaining their history.
- Do not support moving tasks between projects in v1; this preserves stable identifiers and activity references.
- Essential actions must work without drag-and-drop, without color-only meaning, and with keyboard navigation, visible focus, readable text, responsive layouts, and reduced-motion support.
- P0 includes authentication, Projects, Tasks, Subtasks, fixed workflow, priorities, due dates, labels, activity, Board, My Work, progress, Dashboard periods, core Analytics, Search, responsiveness, and accessible controls.
- P1 work is export, richer drill-down, restoration/archive polish, keyboard shortcuts, chart/table toggles, attention indicators, and dashboard customization; P2 deferred features remain out of scope.

## Repository and File Map

The repository currently contains only `planops-complete-spec.md`; the following boundaries are the planned application structure.

**Domain and application code**

- `app/Domain/Identity/` — `User`, `UserPreference`, preference enums, timezone/period value objects, and identity policies.
- `app/Domain/Projects/` — `Project`, project status enum, project Actions, policies, overview/index queries, and progress calculation.
- `app/Domain/Tasks/` — `Task`, task status/priority enums, task Actions, hierarchy rules, policies, progress calculation, board/list queries, and task value objects.
- `app/Domain/Labels/` — `Label`, normalization, label Actions, and ownership rules.
- `app/Domain/Activity/` — `TaskActivity`, activity enum, `TaskActivityRecorder`, feed/timeline queries, and event payload normalization.
- `app/Domain/Dashboard/` — `DashboardQueryService` and dashboard result/value objects.
- `app/Domain/Analytics/` — `AnalyticsQueryService`, median/time-in-status calculators, project-history queries, and chart/table result objects.
- `app/Domain/Search/` — `SearchQueryService` and capped, user-scoped result objects.
- `app/Http/Controllers/`, `app/Http/Requests/`, and `app/Http/Resources/` — thin request/response adapters only.
- `app/Policies/` — `ProjectPolicy`, `TaskPolicy`, and `LabelPolicy`.

**Persistence and delivery**

- `database/migrations/` — the seven core tables, constraints, foreign keys, and indexes.
- `database/factories/` and `database/seeders/` — deterministic test/demo data, including a golden PlanOps scenario.
- `routes/web.php` — explicit page and action routes from the spec.
- `resources/views/layouts/`, `resources/views/components/`, and `resources/views/pages/` — shell, reusable accessible controls, and page templates.
- `resources/views/livewire/` and `app/Livewire/` — board, filters, task detail, and other interactive components if the selected Blade/Livewire adapter needs stateful components.
- `resources/css/app.css` and `resources/js/app.js` — theme tokens, density, focus, reduced motion, and progressive-enhancement behavior.

**Tests and documentation**

- `tests/Unit/Domain/` — status, progress, period, activity, and analytics formulas.
- `tests/Feature/` — authenticated routes, validation, transactions, ownership, and UI-facing workflows.
- `tests/Browser/` and `playwright.config.*` — keyboard, board, dashboard, responsive, and golden E2E flows.
- `docs/architecture/stack.md` — verified local/production stack and infrastructure decisions.
- `docs/architecture/domain-contracts.md` — enums, schemas, Actions, invariants, activity payloads, and metric formulas.
- `docs/ui/screen-spec.md` — route-by-route screen, interaction, empty-state, and accessibility contract.
- `docs/superpowers/plans/2026-08-20-planops-implementation.md` — this execution plan.

## Dependency Order

| Task | Deliverable | Depends on | Priority |
|---|---|---|---|
| DYX-001 | Architecture, domain, and screen contracts | Spec only | P0 |
| DYX-002 | Laravel scaffold, auth, shell, preferences foundation | DYX-001 | P0 |
| DYX-003 | Schema, models, enums, factories, ownership primitives | DYX-002 | P0 |
| DYX-004 | Append-only activity recorder and event contract | DYX-003 | P0 |
| DYX-005 | Project lifecycle and archive/restore | DYX-003, DYX-004 | P0 |
| DYX-006 | Task capture, stable keys, atomic numbering | DYX-004, DYX-005 | P0 |
| DYX-007 | Task metadata, due state, labels, soft deletion | DYX-006 | P0 |
| DYX-008 | Subtasks and derived progress | DYX-006, DYX-007 | P0 |
| DYX-009 | Status transitions, timestamps, reopen semantics | DYX-006, DYX-008 | P0 |
| DYX-010 | Global activity and task timelines | DYX-004, DYX-009 | P0 |
| DYX-011 | Project index and project overview | DYX-005, DYX-008, DYX-010 | P0 |
| DYX-012 | Accessible project board and reordering | DYX-009, DYX-011 | P0 |
| DYX-013 | Project task list and My Work | DYX-007, DYX-009, DYX-012 | P0 |
| DYX-014 | Global search | DYX-006, DYX-007, DYX-013 | P0 |
| DYX-015 | Timezone-aware period boundaries | DYX-002, DYX-003 | P0 |
| DYX-016 | Dashboard periods and trustworthy KPI/chart data | DYX-008, DYX-009, DYX-010, DYX-015 | P0 |
| DYX-017 | Global and project analytics | DYX-008, DYX-009, DYX-010, DYX-015, DYX-016 | P0 |
| DYX-018 | UX, settings, responsive, and accessibility hardening | DYX-012–DYX-017 | P0 |
| DYX-019 | CSV/JSON export and P1 attention polish | DYX-010, DYX-016, DYX-017 | P1 |
| DYX-020 | Security, performance, privacy, and deployment hardening | DYX-018, DYX-019 | P0/P1 |
| DYX-021 | Golden E2E verification and release gate | DYX-020 | P0 |

---

### Task 1 (DYX-001): Lock the architecture, domain, and screen contracts

**Files:**
- Create: `docs/architecture/stack.md`
- Create: `docs/architecture/domain-contracts.md`
- Create: `docs/ui/screen-spec.md`
- Test: documentation checks performed with `rg` and a manual spec review

**Interfaces:**
- Consumes: `planops-complete-spec.md`, especially sections 83–118, 125–148, and 152.
- Produces: the exact stack choice, schema/action names, route map, metric formulas, and UI acceptance contract used by every later task.

- [ ] **Step 1: Record the implementation baseline** — document Laravel 13, PHP 8.3, PostgreSQL, session auth, Blade/Livewire/Tailwind/Vite, Pest/PHPUnit, Playwright/axe-core, and the deliberate absence of queues, schedulers, WebSockets, and external search.
- [ ] **Step 2: Record domain contracts** — document all seven tables, backed enums, ownership invariants, action signatures, transaction boundaries, stable task-key policy, activity payload fields, and the v1 rule that tasks do not move between projects.
- [ ] **Step 3: Record screen contracts** — document routes and required sections for Dashboard, My Work, Projects, Project Overview, Board, Tasks, Task Detail, Activity, Analytics, Search, and Settings, including empty states and keyboard alternatives.
- [ ] **Step 4: Verify coverage** — run `rg -n "Laravel 13|PHP 8.3|TaskActivity|No active scope|No productivity|No drag|Today|Week|Month|Year|P2" docs/architecture docs/ui`; expected: every contract keyword is present in the three documents.
- [ ] **Step 5: Commit** — `git add docs/architecture docs/ui && git commit -m "docs: define PlanOps implementation contracts"`.

### Task 2 (DYX-002): Bootstrap Laravel, authentication, application shell, and preference foundation

**Files:**
- Create/modify: `composer.json`, `package.json`, `.env.example`, `vite.config.*`, `tailwind.config.*`, `app/Models/User.php`
- Create: `app/Domain/Identity/Models/UserPreference.php`, `app/Domain/Identity/Actions/UpdateUserPreferences.php`
- Create: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/SettingsController.php`, `app/Http/Requests/UpdateUserPreferencesRequest.php`
- Create: `resources/views/layouts/app.blade.php`, `resources/views/pages/settings/index.blade.php`, `resources/views/components/navigation/sidebar.blade.php`
- Modify: `routes/web.php`, `resources/css/app.css`, `resources/js/app.js`
- Test: `tests/Feature/Auth/AuthenticationTest.php`, `tests/Feature/Settings/UserPreferencesTest.php`, `tests/Feature/ApplicationShellTest.php`

**Interfaces:**
- Consumes: DYX-001 stack and route contracts.
- Produces: authenticated session middleware, a stable shell/navigation layout, and a preference update action with defaults `Africa/Casablanca`, `MONDAY`, `SYSTEM`, and `COMFORTABLE`.

- [ ] **Step 1: Write failing foundation tests** — assert guests are redirected to login, authenticated users can load `/dashboard`, the global navigation exposes Dashboard/My Work/Projects/Analytics/Activity/Settings, and a user receives the four documented preference defaults.
- [ ] **Step 2: Run `php artisan test tests/Feature/Auth tests/Feature/ApplicationShellTest.php`** — expected: FAIL because the Laravel application and routes do not exist.
- [ ] **Step 3: Scaffold the Laravel 13 application** — install the PHP and frontend dependencies recorded in DYX-001, configure PostgreSQL in `.env.example`, install session authentication, and compile the base asset pipeline.
- [ ] **Step 4: Implement the shell and preferences** — keep controllers thin, protect application routes with `auth`, store IANA timezone strings rather than fixed offsets, validate `MONDAY|SUNDAY`, `SYSTEM|LIGHT|DARK`, and `COMFORTABLE|COMPACT`, and apply theme/density classes to the root layout.
- [ ] **Step 5: Run `php artisan test tests/Feature/Auth tests/Feature/Settings tests/Feature/ApplicationShellTest.php` and `npm run build`** — expected: PASS and a production asset build.
- [ ] **Step 6: Commit** — `git add . && git commit -m "feat: bootstrap authenticated PlanOps shell"`.

### Task 3 (DYX-003): Create the relational schema, models, enums, factories, and ownership primitives

**Files:**
- Create: `database/migrations/*_create_user_preferences_table.php`, `*_create_projects_table.php`, `*_create_tasks_table.php`, `*_create_labels_table.php`, `*_create_task_label_table.php`, `*_create_task_activities_table.php`
- Create: `app/Domain/Projects/Models/Project.php`, `app/Domain/Tasks/Models/Task.php`, `app/Domain/Labels/Models/Label.php`, `app/Domain/Activity/Models/TaskActivity.php`
- Create: `app/Domain/Projects/Enums/ProjectStatus.php`, `app/Domain/Tasks/Enums/TaskStatus.php`, `app/Domain/Tasks/Enums/TaskPriority.php`, `app/Domain/Activity/Enums/TaskActivityType.php`, `app/Domain/Identity/Enums/{ThemePreference,DensityPreference,WeekStartDay}.php`
- Create: `database/factories/{ProjectFactory,TaskFactory,LabelFactory,TaskActivityFactory,UserPreferenceFactory}.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Database/SchemaInvariantTest.php`, `tests/Unit/Domain/Tasks/TaskStatusTest.php`, `tests/Feature/Authorization/OwnershipScopeTest.php`

**Interfaces:**
- Consumes: authenticated `User` from DYX-002.
- Produces: typed models and relationships for `User → Projects → Tasks → TaskActivities`, labels/pivot relations, all backed enums, and database constraints/indexes from spec sections 83–95.

- [x] **Step 1: Write failing schema tests** — assert the seven tables, unique keys `(user_id,key)`, `(project_id,number)`, `(user_id,normalized_name)`, `(task_id,label_id)`, `(user_id)`, task soft deletes, JSON activity columns, and required indexes exist.
- [x] **Step 2: Run `php artisan test tests/Feature/Database/SchemaInvariantTest.php`** — expected: FAIL because the migrations and models are absent.
- [x] **Step 3: Implement migrations and enums** — use UTC-capable timestamps for real timestamps, `date` for `due_on/start_on/target_on`, `jsonb` for activity values/metadata, `next_task_number BIGINT DEFAULT 1`, and foreign keys that preserve task/activity history under normal archive and soft-delete flows.
- [x] **Step 4: Implement relationships and enum mappings** — add `User` ownership relations, same-project task relations, one-level parent/children relations, label pivot relations, `TaskActivity` immutability intent, and `TaskStatus::category()` returning `PLANNED`, `ACTIVE`, or `TERMINAL`.
- [x] **Step 5: Seed deterministic factories** — include users with different timezones, projects in each lifecycle state, top-level tasks, subtasks, labels, status events, deleted tasks, and reopened tasks without introducing any deferred model.
- [x] **Step 6: Run `php artisan migrate:fresh --seed` and `php artisan test tests/Feature/Database tests/Unit/Domain/Tasks/TaskStatusTest.php`** — expected: PASS and a reproducible database.
- [x] **Step 7: Commit** — `git add app database tests && git commit -m "feat: add PlanOps domain schema and enums"`.

### Task 4 (DYX-004): Implement the append-only activity recorder and event payload contract

**Files:**
- Create: `app/Domain/Activity/Services/TaskActivityRecorder.php`
- Create: `app/Domain/Activity/Queries/TaskActivityFeedQuery.php`
- Modify: `app/Domain/Activity/Models/TaskActivity.php`
- Test: `tests/Unit/Domain/Activity/TaskActivityRecorderTest.php`, `tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php`

**Interfaces:**
- Consumes: `Task`, `Project`, `User`, `TaskActivityType`, and the activity table from DYX-003.
- Produces: `TaskActivityRecorder::record(Task $task, TaskActivityType $type, ?string $field, mixed $oldValue, mixed $newValue, array $metadata = []): TaskActivity`.

- [x] **Step 1: Write failing recorder tests** — verify task context is copied to `user_id/project_id/task_id`, status values are stored as stable enum strings, sensitive title/description text is not copied into generic update payloads, and status reopen metadata is preserved.
- [x] **Step 2: Run `php artisan test tests/Unit/Domain/Activity tests/Feature/Domain/Activity`** — expected: FAIL because the recorder is absent.
- [x] **Step 3: Implement normalized recording** — centralize payload shape for `TASK_CREATED`, `TASK_UPDATED`, `STATUS_CHANGED`, `PRIORITY_CHANGED`, `DUE_DATE_CHANGED`, `LABEL_ADDED`, `LABEL_REMOVED`, `SUBTASK_CREATED`, `TASK_DELETED`, and `TASK_RESTORED`; keep `TASK_MOVED_PROJECT` defined but unreachable in v1 UI.
- [x] **Step 4: Enforce append-only application behavior** — expose read relations and queries but no update/delete UI or action; add a model-level test that normal application flows never mutate existing activity rows.
- [x] **Step 5: Run the activity tests** — expected: PASS with one consistent JSON shape per event type.
- [x] **Step 6: Commit** — `git add app/Domain/Activity tests && git commit -m "feat: add append-only task activity recorder"`.

### Task 5 (DYX-005): Implement project lifecycle, keys, and archive/restore

**Files:**
- Create: `app/Domain/Projects/Actions/{CreateProject,UpdateProject,ChangeProjectStatus,ArchiveProject,RestoreProject}.php`
- Create: `app/Domain/Projects/Queries/ProjectIndexQuery.php`
- Create: `app/Policies/ProjectPolicy.php`
- Create: `app/Http/Controllers/ProjectController.php`, `app/Http/Requests/{StoreProjectRequest,UpdateProjectRequest,ChangeProjectStatusRequest}.php`
- Create: `resources/views/pages/projects/index.blade.php`, `resources/views/pages/projects/create.blade.php`, `resources/views/pages/projects/edit.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Domain/Projects/ProjectKeyTest.php`, `tests/Feature/Projects/ProjectManagementTest.php`

**Interfaces:**
- Consumes: DYX-003 models/enums and DYX-004 activity conventions.
- Produces: `CreateProject::handle(User $user, array $attributes): Project`, explicit lifecycle Actions, `ProjectPolicy`, and project index filtering/sorting by status, archive state, target date, name, progress, and updated date.

- [x] **Step 1: Write failing project tests** — cover required name, `^[A-Z0-9]{2,10}$` key validation, per-user key uniqueness, target date not before start date, manual status changes, and archive/restore without deleting tasks or activity.
- [ ] **Step 2: Run `php artisan test tests/Unit/Domain/Projects tests/Feature/Projects`** — expected: FAIL before Actions, policies, routes, and views exist.
- [x] **Step 3: Implement project Actions and requests** — reject key changes once the project has a task, never auto-change project status when tasks become Done, and use policy checks before every action.
- [x] **Step 4: Implement project pages** — expose create/edit, lifecycle controls, archive/restore, filters, sort options, and actionable validation errors using canonical terminology `Project`, never `Issue` or `Ticket`.
- [ ] **Step 5: Run the tests and verify `php artisan route:list --path=projects`** — expected: all project feature tests pass and only explicit project routes are exposed.
- [x] **Step 6: Commit** — `git add app resources routes tests && git commit -m "feat: add project lifecycle management"`.

> Verification note: Steps 2 and 5 remain pending because this host does not have the required PHP 8.3 executable. The UI asset build and static contract review pass locally; PHP feature tests, route listing, and browser rendering still need a PHP-enabled environment.

### Task 6 (DYX-006): Implement task capture, stable task keys, and atomic numbering

**Files:**
- Create: `app/Domain/Tasks/Actions/CreateTask.php`, `app/Domain/Tasks/Queries/TaskKeyQuery.php`
- Create: `app/Http/Controllers/TaskController.php`, `app/Http/Requests/StoreTaskRequest.php`
- Create: `app/Policies/TaskPolicy.php`
- Create: `resources/views/pages/tasks/create.blade.php`, `resources/views/components/tasks/quick-create.blade.php`
- Modify: `routes/web.php`, `app/Domain/Tasks/Models/Task.php`
- Test: `tests/Unit/Domain/Tasks/TaskKeyTest.php`, `tests/Feature/Tasks/CreateTaskTest.php`, `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`

**Interfaces:**
- Consumes: owned `Project`, `TaskActivityRecorder`, `TaskStatus`, and `TaskPriority` from DYX-003–005.
- Produces: `CreateTask::handle(User $user, Project $project, array $attributes): Task`, a derived display key `{project.key}-{task.number}`, and a quick-create form with required `Project` and `Title` only.

- [x] **Step 1: Write failing task tests** — assert defaults `NOT_STARTED` and `MEDIUM`, project-local keys such as `PLAN-1`, `TASK_CREATED` activity, no number reuse after soft delete, and rejection of a parent from another user/project.
- [ ] **Step 2: Run `php artisan test tests/Feature/Tasks/CreateTaskTest.php tests/Feature/Tasks/TaskNumberConcurrencyTest.php`** — expected: FAIL because `CreateTask` and number allocation are absent.
- [x] **Step 3: Implement the transaction** — lock the project row for update, read `next_task_number`, create the task, increment the counter, record `TASK_CREATED`, and commit as one transaction; set `status_changed_at` at creation.
- [x] **Step 4: Implement the quick-create UI** — keep `Project` and `Title` visible, apply documented defaults, and reveal description/status/priority/due date/parent as optional controls.
- [ ] **Step 5: Run the task tests and `php artisan test --parallel`** — expected: PASS, including concurrent allocation without duplicate `(project_id, number)` values.
- [x] **Step 6: Commit** — `git add app resources routes tests && git commit -m "feat: add atomic task creation"`.

> Verification note: Steps 2 and 5 remain pending because this host does not have the required PHP executable. PHP tests, route listing, and browser rendering still require a PHP-enabled environment.

### Task 7 (DYX-007): Add task metadata, computed due state, labels, and soft deletion

**Files:**
- Create: `app/Domain/Tasks/Actions/{UpdateTask,ChangeTaskPriority,ChangeTaskDueDate,DeleteTask,RestoreTask}.php`
- Create: `app/Domain/Tasks/Rules/OverdueTask.php`
- Create: `app/Domain/Labels/Actions/{CreateLabel,AttachLabelToTask,DetachLabelFromTask,DeleteLabel}.php`
- Create: `app/Domain/Labels/Rules/NormalizedLabelName.php`, `app/Policies/LabelPolicy.php`
- Create: `app/Http/Requests/{ChangeTaskPriorityRequest,ChangeTaskDueDateRequest,StoreLabelRequest}.php`
- Create: `resources/views/components/tasks/metadata-form.blade.php`, `resources/views/components/labels/label-picker.blade.php`
- Test: `tests/Unit/Domain/Tasks/OverdueTaskTest.php`, `tests/Unit/Domain/Labels/LabelNormalizationTest.php`, `tests/Feature/Tasks/TaskMetadataTest.php`, `tests/Feature/Labels/LabelManagementTest.php`

**Interfaces:**
- Consumes: `Task`, `Label`, activity recorder, and policies from DYX-003–006.
- Produces: metadata Actions, `Task::isOverdueOn(CarbonImmutable $userLocalDate): bool`, normalized per-user labels, and soft-delete/restore flows.

- [ ] **Step 1: Write failing tests** — cover title/description edits, `LOW|MEDIUM|HIGH|URGENT`, date-only due dates, overdue only when local date is after `due_on` and status is not `DONE|CANCELLED`, label normalization/uniqueness, detach-on-label-delete, and active-view exclusion after soft delete.
- [ ] **Step 2: Run the focused tests** — expected: FAIL because the Actions and rule classes are absent.
- [ ] **Step 3: Implement metadata Actions** — emit one meaningful activity event per domain mutation, avoid copying full descriptions into old/new JSON, and reject cross-user label attachment.
- [ ] **Step 4: Implement soft deletion** — require confirmation in the UI, emit `TASK_DELETED`/`TASK_RESTORED`, retain identifiers/history, and ensure normal queries use `withTrashed` only for explicit restoration/history contexts.
- [ ] **Step 5: Run `php artisan test tests/Unit/Domain/Tasks tests/Unit/Domain/Labels tests/Feature/Tasks tests/Feature/Labels`** — expected: PASS.
- [ ] **Step 6: Commit** — `git add app resources tests && git commit -m "feat: add task metadata labels and soft deletion"`.

### Task 8 (DYX-008): Implement one-level subtasks and derived progress

**Files:**
- Create: `app/Domain/Tasks/Actions/CreateSubtask.php`
- Create: `app/Domain/Tasks/Rules/{SameProjectParent,TopLevelOnly}.php`
- Create: `app/Domain/Tasks/Services/{ProjectProgressCalculator,SubtaskProgressCalculator}.php`
- Create: `resources/views/components/tasks/subtask-list.blade.php`
- Test: `tests/Unit/Domain/Tasks/ProjectProgressCalculatorTest.php`, `tests/Unit/Domain/Tasks/SubtaskProgressCalculatorTest.php`, `tests/Feature/Tasks/SubtaskHierarchyTest.php`

**Interfaces:**
- Consumes: `CreateTask`, task relationships, soft-delete scope, and statuses from DYX-006–007.
- Produces: `CreateSubtask::handle(User $user, Task $parent, array $attributes): Task`, `ProjectProgressCalculator::calculate(Project $project): ProjectProgress`, and `SubtaskProgressCalculator::calculate(Task $parent): SubtaskProgress`.

- [ ] **Step 1: Write failing hierarchy/progress tests** — reject self-parenting, parent/child project mismatch, child-of-subtask creation, and cross-user parents; assert cancelled subtasks are excluded from subtask percentage.
- [ ] **Step 2: Write the acceptance fixture** — Task A Done with 10 subtasks, Task B Done with 1, Task C In Progress, Task D Cancelled; assert project progress is `2 / 3 = 66.67%` and subtask count does not weight project progress.
- [ ] **Step 3: Run `php artisan test tests/Unit/Domain/Tasks/*Progress* tests/Feature/Tasks/SubtaskHierarchyTest.php`** — expected: FAIL before calculators and rules exist.
- [ ] **Step 4: Implement the rules and calculators** — use only non-deleted top-level tasks, exclude `CANCELLED`, return `0%` plus `No active scope` for zero eligible tasks, and never auto-change a parent status when children change.
- [ ] **Step 5: Implement the compact subtask list** — show `completed / total`, each child status with text, and a clear suggestion only when all non-cancelled children are Done; do not perform automatic parent mutation.
- [ ] **Step 6: Run the focused tests** — expected: PASS.
- [ ] **Step 7: Commit** — `git add app resources tests && git commit -m "feat: add one-level subtasks and derived progress"`.

### Task 9 (DYX-009): Implement fixed workflow transitions, timestamps, and reopen semantics

**Files:**
- Create: `app/Domain/Tasks/Actions/ChangeTaskStatus.php`
- Create: `app/Domain/Tasks/Queries/TaskStatusQuery.php`
- Modify: `app/Domain/Tasks/Enums/TaskStatus.php`, `app/Domain/Tasks/Models/Task.php`
- Create: `resources/views/components/tasks/status-control.blade.php`
- Test: `tests/Unit/Domain/Tasks/TaskStatusTransitionTest.php`, `tests/Feature/Tasks/ChangeTaskStatusTest.php`

**Interfaces:**
- Consumes: task Actions, `TaskActivityRecorder`, `TaskStatus`, `ProjectProgressCalculator`, and ownership policies from DYX-004–008.
- Produces: `ChangeTaskStatus::handle(User $user, Task $task, TaskStatus $newStatus, CarbonImmutable $at): Task` with direct transitions allowed and no transition matrix.

- [ ] **Step 1: Write failing transition tests** — cover first `IN_PROGRESS` setting `first_started_at`, later resumes preserving it, `DONE` setting `completed_at`/clearing `cancelled_at`, `CANCELLED` setting `cancelled_at`/clearing `completed_at`, and reopening either terminal state clearing its current timestamp.
- [ ] **Step 2: Add reopen assertions** — for `IN_PROGRESS → DONE → IN_PROGRESS`, assert two status events, `metadata.is_reopen = true` on the second transition, distinct reopened KPI eligibility, and current project progress decreasing after reopen while historical completion remains.
- [ ] **Step 3: Run `php artisan test tests/Unit/Domain/Tasks/TaskStatusTransitionTest.php tests/Feature/Tasks/ChangeTaskStatusTest.php`** — expected: FAIL before the Action exists.
- [ ] **Step 4: Implement the Action transaction** — authorize the task, no-op identical status changes, update `status`, `status_changed_at`, and lifecycle timestamps atomically, then record one `STATUS_CHANGED` event with stable old/new values and reopen metadata.
- [ ] **Step 5: Implement the status control** — list all seven statuses with text, allow direct movement, require no confirmation for normal changes, and make cancellation confirmation lightweight and explicit.
- [ ] **Step 6: Run the tests plus `php artisan test --parallel`** — expected: PASS.
- [ ] **Step 7: Commit** — `git add app resources tests && git commit -m "feat: implement task workflow transitions"`.

### Task 10 (DYX-010): Build global activity and task activity timelines

**Files:**
- Create: `app/Domain/Activity/Queries/{GlobalActivityFeedQuery,TaskTimelineQuery}.php`
- Create: `app/Http/Controllers/ActivityController.php`
- Create: `resources/views/pages/activity/index.blade.php`, `resources/views/components/activity/timeline.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Activity/GlobalActivityFeedTest.php`, `tests/Feature/Activity/TaskTimelineTest.php`

**Interfaces:**
- Consumes: append-only events from DYX-004 and transition events from DYX-009.
- Produces: global activity route `/activity`, task activity section on `/tasks/{task}`, filters for project/event type/date/task, global newest-first pagination of 50, and task chronological timeline.

- [ ] **Step 1: Write failing feed tests** — seed activity across two users/projects, assert strict user scoping, filter combinations, pagination size 50, global newest-first order, and task oldest-first timeline order.
- [ ] **Step 2: Run `php artisan test tests/Feature/Activity`** — expected: FAIL because routes, queries, and views are absent.
- [ ] **Step 3: Implement query services** — filter by UTC-converted period bounds, join only owned projects/tasks, return display-ready event labels from enums, and never expose raw JSON as the primary UI.
- [ ] **Step 4: Implement the timeline UI** — group by local date, show key/title, event text, old/new values where safe, and concise empty states such as `No task state changes were recorded in this period.`
- [ ] **Step 5: Run the feed tests** — expected: PASS.
- [ ] **Step 6: Commit** — `git add app resources routes tests && git commit -m "feat: add activity feeds and task timelines"`.

### Task 11 (DYX-011): Implement project index and project overview screens

**Files:**
- Create: `app/Domain/Projects/Queries/ProjectOverviewQuery.php`
- Create: `resources/views/pages/projects/show.blade.php`, `resources/views/components/projects/project-card.blade.php`, `resources/views/components/projects/project-navigation.blade.php`
- Modify: `app/Http/Controllers/ProjectController.php`, `routes/web.php`
- Test: `tests/Feature/Projects/ProjectIndexScreenTest.php`, `tests/Feature/Projects/ProjectOverviewScreenTest.php`

**Interfaces:**
- Consumes: project Actions, progress calculators, task status queries, and activity queries from DYX-005, DYX-008–010.
- Produces: `/projects` and `/projects/{project}` pages showing current health, progress, In Progress/In Review/Blocked work, recent activity, upcoming/overdue work, and local navigation for Overview/Board/Tasks/Activity/Analytics/Settings.

- [ ] **Step 1: Write failing screen tests** — assert owned active/archived filtering, project card progress, status counts, `No active scope`, manual project status display, recent activity, and no auto-completion suggestion being applied.
- [ ] **Step 2: Run `php artisan test tests/Feature/Projects/*ScreenTest.php`** — expected: FAIL before the query and views exist.
- [ ] **Step 3: Implement the overview/index query composition** — eager-load bounded relationships to avoid N+1 queries, use top-level active task counts, and keep archived projects out of default active results while preserving direct historical access.
- [ ] **Step 4: Implement desktop/tablet/mobile layouts** — show readable text status chips, concise cards, target date, progress, current-work counts, and actionable empty states.
- [ ] **Step 5: Run the screen tests** — expected: PASS.
- [ ] **Step 6: Commit** — `git add app resources routes tests && git commit -m "feat: add project index and overview"`.

### Task 12 (DYX-012): Implement the project board, accessible status controls, and transactional reorder

**Files:**
- Create: `app/Domain/Tasks/Actions/ReorderTasks.php`
- Create: `app/Domain/Tasks/Queries/ProjectBoardQuery.php`
- Create: `app/Http/Requests/ReorderTasksRequest.php`
- Create: `app/Livewire/Projects/ProjectBoard.php` or the equivalent interactive component selected in DYX-001
- Create: `resources/views/pages/projects/board.blade.php`, `resources/views/livewire/projects/project-board.blade.php`, `resources/views/components/tasks/task-card.blade.php`
- Modify: `routes/web.php`, `resources/js/app.js`
- Test: `tests/Unit/Domain/Tasks/ReorderTasksTest.php`, `tests/Feature/Projects/ProjectBoardTest.php`, `tests/Browser/board-accessibility.spec.js`

**Interfaces:**
- Consumes: `ChangeTaskStatus`, task cards, project overview, and task positions from DYX-009–011.
- Produces: `ProjectBoardQuery::for(Project $project, User $user): BoardData`, `ReorderTasks::handle(User $user, Project $project, TaskStatus $status, array $orderedTaskIds): void`, six default non-cancelled columns, and a non-drag move operation on every card.

- [ ] **Step 1: Write failing domain tests** — assert board returns only owned, non-deleted, top-level tasks; groups by detailed status; and reorder updates affected positions in one transaction while rejecting foreign/incorrect-status IDs.
- [ ] **Step 2: Write failing browser tests** — keyboard-focus a card, open its status control, move `NOT_STARTED → IN_PROGRESS` without dragging, and assert visible focus plus text status; also cover pointer drag when enabled.
- [ ] **Step 3: Run `php artisan test tests/Unit/Domain/Tasks/ReorderTasksTest.php tests/Feature/Projects/ProjectBoardTest.php`** — expected: FAIL before query/action/component exist.
- [ ] **Step 4: Implement the board** — render `BACKLOG | NOT STARTED | IN PROGRESS | IN REVIEW | BLOCKED | DONE`, keep Cancelled behind a filter, show key/title/priority/due date/labels/subtask progress, call `ChangeTaskStatus` for status moves, and call `ReorderTasks` for same-column order changes.
- [ ] **Step 5: Add optional drag enhancement** — keep the button/menu path complete and keyboard operable; dragging must never be the only way to change status.
- [ ] **Step 6: Run unit/feature/browser checks** — expected: PASS, with no color-only status meaning.
- [ ] **Step 7: Commit** — `git add app resources routes tests && git commit -m "feat: add accessible project board"`.

### Task 13 (DYX-013): Implement project task lists and the cross-project My Work view

**Files:**
- Create: `app/Domain/Tasks/Queries/{ProjectTaskListQuery,MyWorkQuery}.php`
- Create: `app/Http/Requests/MyWorkFiltersRequest.php`
- Create: `app/Http/Controllers/MyWorkController.php`
- Create: `resources/views/pages/projects/tasks.blade.php`, `resources/views/pages/my-work/index.blade.php`, `resources/views/components/tasks/task-table.blade.php`, `resources/views/components/filters/task-filters.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tasks/ProjectTaskListTest.php`, `tests/Feature/MyWork/MyWorkFiltersTest.php`, `tests/Feature/MyWork/MyWorkSortingTest.php`

**Interfaces:**
- Consumes: task metadata/status/label queries from DYX-007–009 and project data from DYX-011–012.
- Produces: `/projects/{project}/tasks` and `/my-work`; filters Project/Status/Priority/Label/Due state/Created date/Updated date; due shortcuts; sorts Recently updated/created/Priority/Due date/Task key/Project; default sort Recently updated; pagination of 50 tasks.

- [ ] **Step 1: Write failing query tests** — assert default My Work statuses emphasize `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, `NOT_STARTED`, while Backlog/Done remain filterable; verify due shortcuts and every sort option.
- [ ] **Step 2: Run `php artisan test tests/Feature/Tasks/ProjectTaskListTest.php tests/Feature/MyWork`** — expected: FAIL before query services/routes exist.
- [ ] **Step 3: Implement typed filters and queries** — apply user scoping first, exclude soft-deleted tasks, include project/key/labels in the result, use date-only due logic against the user’s local date, and prevent arbitrary sort-column injection.
- [ ] **Step 4: Implement list/table UI** — render Key/Title/Status/Priority/Due/Labels/Subtasks/Updated, support quick status/priority changes, open task detail without losing context, and provide table/list empty states.
- [ ] **Step 5: Run the feature tests and `php artisan test --parallel`** — expected: PASS.
- [ ] **Step 6: Commit** — `git add app resources routes tests && git commit -m "feat: add task lists and My Work"`.

### Task 14 (DYX-014): Implement keyboard-navigable global search

**Files:**
- Create: `app/Domain/Search/Queries/SearchQueryService.php`
- Create: `app/Http/Controllers/SearchController.php`, `app/Http/Requests/SearchRequest.php`
- Create: `resources/views/pages/search/index.blade.php`, `resources/views/components/search/result-list.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/app.blade.php`, `resources/js/app.js`
- Test: `tests/Feature/Search/SearchQueryTest.php`, `tests/Browser/search-keyboard.spec.js`

**Interfaces:**
- Consumes: owned task/project/label data from DYX-005–007.
- Produces: `SearchQueryService::search(User $user, string $term): SearchResults`, capped at 20 tasks and 20 projects per result type, matching task key/title/description/project name/labels and returning display fields key/title/project/status or project name/key/status/progress.

- [ ] **Step 1: Write failing search tests** — cover `G05`, title, description, project, and label matches; strict user scoping; soft-delete exclusion; caps; empty and short input behavior.
- [ ] **Step 2: Run `php artisan test tests/Feature/Search/SearchQueryTest.php`** — expected: FAIL because the query and route do not exist.
- [ ] **Step 3: Implement PostgreSQL `ILIKE` search** — use parameterized queries, escaped input, explicit result ordering, eager-loaded project/label data, and no external search infrastructure.
- [ ] **Step 4: Implement the global control** — keep search available from major screens, expose results as keyboard-navigable links, preserve visible status text, and return an actionable no-results state.
- [ ] **Step 5: Run feature and browser tests** — expected: PASS.
- [ ] **Step 6: Commit** — `git add app resources routes tests && git commit -m "feat: add global PlanOps search"`.

### Task 15 (DYX-015): Implement timezone-aware Today/Week/Month/Year/custom period boundaries

**Files:**
- Create: `app/Domain/Identity/ValueObjects/ReportPeriod.php`
- Create: `app/Domain/Identity/Services/UserPeriodResolver.php`
- Create: `app/Http/Requests/ReportPeriodRequest.php`
- Test: `tests/Unit/Domain/Identity/UserPeriodResolverTest.php`, `tests/Feature/Settings/TimezoneBoundaryTest.php`

**Interfaces:**
- Consumes: `UserPreference` from DYX-002–003 and Carbon/PostgreSQL UTC timestamps.
- Produces: `ReportPeriod` containing local label, UTC-inclusive `start`, UTC-exclusive `end`, and bucket type `day|week|month`; resolver methods `today`, `week`, `month`, `year`, and `custom`.

- [ ] **Step 1: Write failing period tests** — assert Casablanca local-day conversion, Monday/Sunday week starts, month/year bounds, custom end-date inclusivity, invalid reversed ranges, and UTC query boundaries.
- [ ] **Step 2: Run `php artisan test tests/Unit/Domain/Identity/UserPeriodResolverTest.php tests/Feature/Settings/TimezoneBoundaryTest.php`** — expected: FAIL before the value object/resolver exist.
- [ ] **Step 3: Implement the resolver** — resolve local boundaries in the configured IANA timezone, convert them to UTC only at the query boundary, use half-open intervals `[start,end)`, and return readable labels for the UI.
- [ ] **Step 4: Run the focused tests** — expected: PASS, including a date boundary near midnight.
- [ ] **Step 5: Commit** — `git add app tests && git commit -m "feat: add timezone-aware report periods"`.

### Task 16 (DYX-016): Implement Dashboard query contracts and Today/Week/Month/Year views

**Files:**
- Create: `app/Domain/Dashboard/Queries/DashboardQueryService.php`
- Create: `app/Domain/Dashboard/ValueObjects/DashboardSnapshot.php`
- Create: `app/Http/Requests/DashboardPeriodRequest.php`
- Create: `resources/views/pages/dashboard/index.blade.php`, `resources/views/components/dashboard/{kpi-card,period-selector,created-completed-chart,status-distribution,project-contribution,attention-list}.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`, `routes/web.php`
- Test: `tests/Unit/Domain/Dashboard/DashboardMetricTest.php`, `tests/Feature/Dashboard/DashboardPeriodTest.php`, `tests/Feature/Dashboard/DashboardOwnershipTest.php`

**Interfaces:**
- Consumes: `ReportPeriod`, task/activity/project queries, progress calculators, and preferences from DYX-008–010 and DYX-015.
- Produces: `DashboardQueryService::for(User $user, ReportPeriod $period): DashboardSnapshot` with current-state metrics, period-event metrics, chart buckets, contribution, attention lists, recent activity, and source/denominator/no-data metadata for each metric.

- [ ] **Step 1: Write failing metric tests** — assert current KPIs ignore period (`Active Projects`, current In Progress/In Review/Blocked/Overdue), period metrics count distinct top-level tasks, soft-deleted tasks are excluded, cancelled tasks are hidden from main status distribution, and current-state semantics remain stable across period switches.
- [ ] **Step 2: Add formula tests** — assert Created uses top-level task creation, Completed uses distinct top-level `STATUS_CHANGED → DONE`, Started/Review/Blocked use distinct transition targets, Reopened uses terminal-to-nonterminal transitions, and `balance = created - completed` per bucket.
- [ ] **Step 3: Run `php artisan test tests/Unit/Domain/Dashboard tests/Feature/Dashboard`** — expected: FAIL before query/service/view implementations.
- [ ] **Step 4: Implement query service** — return Today sections (In Progress/In Review/Blocked/Due Today/Overdue/activity), Week throughput, Month broader movement, Year monthly/cumulative summaries, and Custom range data using configured timezone boundaries.
- [ ] **Step 5: Implement the dashboard** — keep the period selector visible, show exact labels such as `Tracked Work Activity`, never `Productivity`, render numeric/chart table summaries, and display explicit no-data messages rather than misleading time-metric zeroes.
- [ ] **Step 6: Run feature tests and `npm run build`** — expected: PASS and compiled dashboard assets.
- [ ] **Step 7: Commit** — `git add app resources routes tests && git commit -m "feat: add period-aware PlanOps dashboard"`.

### Task 17 (DYX-017): Implement global and project analytics from recorded facts

**Files:**
- Create: `app/Domain/Analytics/Queries/AnalyticsQueryService.php`
- Create: `app/Domain/Analytics/Services/{LeadTimeCalculator,CycleTimeCalculator,TimeInStatusCalculator,ProjectProgressHistoryCalculator}.php`
- Create: `app/Domain/Analytics/ValueObjects/AnalyticsSnapshot.php`
- Create: `app/Http/Controllers/AnalyticsController.php`, `app/Http/Controllers/ProjectAnalyticsController.php`
- Create: `resources/views/pages/analytics/index.blade.php`, `resources/views/pages/projects/analytics.blade.php`, `resources/views/components/analytics/{metric-summary,chart-table,heatmap}.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Domain/Analytics/{AnalyticsMetricTest,TimeInStatusCalculatorTest,ProjectProgressHistoryCalculatorTest}.php`, `tests/Feature/Analytics/AnalyticsScreenTest.php`, `tests/Feature/Analytics/AnalyticsOwnershipTest.php`

**Interfaces:**
- Consumes: activity events, task lifecycle fields, report periods, project progress, and dashboard series from DYX-008–010 and DYX-015–016.
- Produces: `AnalyticsQueryService::for(User $user, ReportPeriod $period): AnalyticsSnapshot` for Overview/Throughput/Workflow/Projects/Activity and a project-scoped equivalent.

- [ ] **Step 1: Write failing analytics tests** — cover created/completed/started/reviewed/blocked/reopened distinct counts, project contribution denominator, median lead time, median cycle time, time-in-status reconstruction, heatmap event selection, and project progress decreasing when new scope is added.
- [ ] **Step 2: Fix the v1 repeated-completion interpretation in the contract** — for lead/cycle metrics, use the first `DONE` transition for each distinct task within the selected period, label the result `First completion in period`, and leave every transition visible in Activity.
- [ ] **Step 3: Run `php artisan test tests/Unit/Domain/Analytics tests/Feature/Analytics`** — expected: FAIL before calculators and query service exist.
- [ ] **Step 4: Implement analytics calculations** — derive elapsed calendar time only; initialize status at task creation, close intervals at transitions/period end, cap by period bounds, include only tasks with `first_started_at` for cycle time, and use median rather than average for recommended summaries.
- [ ] **Step 5: Implement screens and accessible summaries** — provide Overview/Throughput/Workflow/Projects/Activity sections, project progress history, charts with labels/tooltips, and table/text summaries; say `Not enough completed tasks in this period to calculate cycle time.` when data is insufficient.
- [ ] **Step 6: Run the focused tests and compare a seeded dashboard with analytics** — expected: PASS and matching counts for the same period.
- [ ] **Step 7: Commit** — `git add app resources routes tests && git commit -m "feat: add fact-based PlanOps analytics"`.

### Task 18 (DYX-018): Harden settings, UX, responsive behavior, and accessibility

**Files:**
- Modify: `app/Http/Controllers/SettingsController.php`, `app/Domain/Identity/Actions/UpdateUserPreferences.php`
- Modify: `resources/views/pages/settings/index.blade.php`, `resources/views/layouts/app.blade.php`, `resources/css/app.css`, `resources/js/app.js`
- Modify: `resources/views/components/{navigation,dialogs,forms,empty-state}.blade.php`
- Create: `tests/Browser/accessibility-core.spec.js`, `tests/Browser/responsive-layout.spec.js`, `tests/Browser/reduced-motion.spec.js`
- Test: `tests/Feature/Settings/PreferencePersistenceTest.php`, browser tests using Playwright/axe-core

**Interfaces:**
- Consumes: all P0 screens and controls from DYX-002 and DYX-011–017.
- Produces: complete settings UI for timezone/week start/theme/density, keyboard-operable core workflows, visible focus in both themes, responsive layouts, accessible chart tables, consistent empty states, and reduced-motion behavior.

- [ ] **Step 1: Write failing browser checks** — complete task creation by keyboard, move a board card without drag, use filters/search, close a dialog and restore focus, inspect status without color, and switch light/dark/system plus comfortable/compact density.
- [ ] **Step 2: Run `npx playwright test tests/Browser/accessibility-core.spec.js`** — expected: FAIL until controls, focus management, and pages are hardened.
- [ ] **Step 3: Implement preference persistence and UI behavior** — apply system/light/dark theme, comfortable/compact density, IANA timezone and week start, and `prefers-reduced-motion`; keep transitions nonessential to understanding state.
- [ ] **Step 4: Harden interactions** — ensure pointer targets are comfortable, all sidebar links/forms/dialogs/search/filter/status/priority controls have accessible names, focus rings are visible, modal focus returns to its trigger, and no status is color-only.
- [ ] **Step 5: Harden responsive layouts** — persistent desktop sidebar, collapsible tablet sidebar, horizontally scrollable board, list-first mobile project view, simplified charts with numeric summaries, and primary non-drag status controls on mobile.
- [ ] **Step 6: Run `npx playwright test tests/Browser` and `php artisan test`** — expected: PASS with no critical axe violations and no regressions.
- [ ] **Step 7: Commit** — `git add app resources tests && git commit -m "feat: harden PlanOps UX and accessibility"`.

### Task 19 (DYX-019): Add P1 export and attention-indicator polish

**Files:**
- Create: `app/Domain/Export/Queries/ExportQueryService.php`
- Create: `app/Http/Controllers/ExportController.php`, `app/Http/Requests/ExportRequest.php`
- Create: `resources/views/components/attention/stale-task-indicator.blade.php`
- Modify: `routes/web.php`, `resources/views/pages/analytics/index.blade.php`, `resources/views/pages/projects/show.blade.php`
- Test: `tests/Feature/Export/ExportTest.php`, `tests/Feature/Attention/AttentionIndicatorTest.php`

**Interfaces:**
- Consumes: owned project/task/activity/label queries from DYX-005–010 and analytics period data from DYX-016–017.
- Produces: user-scoped Projects CSV, Tasks CSV with `key/project/parent/title/status/priority/due_on/labels/created_at/updated_at`, Activity CSV/JSON, and clearly labeled attention indicators for overdue/blocked/long-review/stale work.

- [ ] **Step 1: Write failing export tests** — assert exact headers, stable task keys, label serialization, activity JSON event fields, user scoping, soft-delete policy, and content-disposition filenames.
- [ ] **Step 2: Implement streamed export responses** — query in bounded chunks, avoid loading an entire history into memory, omit arbitrary task body content from exports unless the export contract explicitly includes it, and never expose another user’s rows.
- [ ] **Step 3: Implement attention indicators** — use overdue/blocked/long-review and the fixed baseline stale rule `status IN (IN_PROGRESS, IN_REVIEW, BLOCKED) AND updated_at < now - 7 days`; label indicators as suggestions, never mutate state.
- [ ] **Step 4: Run `php artisan test tests/Feature/Export tests/Feature/Attention`** — expected: PASS.
- [ ] **Step 5: Commit** — `git add app resources routes tests && git commit -m "feat: add PlanOps export and attention indicators"`.

### Task 20 (DYX-020): Harden security, privacy, performance, and deployment

**Files:**
- Modify: `app/Policies/{ProjectPolicy,TaskPolicy,LabelPolicy}.php`, `app/Http/Controllers/*`, `app/Http/Requests/*`
- Create: `app/Http/Middleware/AttachRequestCorrelationId.php`, `app/Http/Middleware/RateLimitSearch.php`
- Modify: `bootstrap/app.php`, `config/logging.php`, `config/session.php`, `config/app.php`, `.env.example`
- Create: `docs/deployment/zero-cost-stack.md`, `docs/deployment/production-checklist.md`
- Test: `tests/Feature/Security/AuthorizationAuditTest.php`, `tests/Feature/Security/ValidationAndCsrfTest.php`, `tests/Feature/Performance/QueryCountTest.php`

**Interfaces:**
- Consumes: all routes/actions/query services from DYX-002–019.
- Produces: authenticated/authorized production configuration, safe logging/correlation IDs, request validation/rate limits, no full task descriptions in logs, pagination/performance evidence, and a deployment procedure that does not require queues/schedulers/WebSockets for core behavior.

- [ ] **Step 1: Write failing security tests** — attempt cross-user project/task/label IDs, forged mass-assignment fields, CSRF-less mutations, invalid enum/date inputs, unauthorized restore/export/search, and production-style hidden stack traces.
- [ ] **Step 2: Write failing performance checks** — assert project/task lists do not issue N+1 relationship queries, default Tasks/Activity/Search page sizes are 50/50/20, and core dashboard queries use documented indexes.
- [ ] **Step 3: Implement hardening** — apply policies consistently, authorize before queries where possible, keep CSRF/session security, disable debug in production, secure cookies under HTTPS, escape/sanitize rendered content, add request IDs to unexpected production errors, and rate-limit plausible abuse.
- [ ] **Step 4: Verify privacy behavior** — inspect logs and activity payloads for absence of full descriptions, arbitrary form bodies, and sensitive content; preserve only IDs/statuses/timestamps needed for analytics.
- [ ] **Step 5: Profile and document deployment** — run migrations/seeding/build in a clean environment, record PostgreSQL connection/backup expectations, verify no permanent worker/scheduler/WebSocket is required, and document rollback/health checks.
- [ ] **Step 6: Run security/performance tests and `php artisan optimize`** — expected: PASS with measured query counts and a production build.
- [ ] **Step 7: Commit** — `git add app bootstrap config docs/deployment tests .env.example && git commit -m "chore: harden PlanOps for production"`.

### Task 21 (DYX-021): Verify the golden scenario and release gate

**Files:**
- Create: `tests/Browser/golden-planops-flow.spec.js`
- Create/modify: `database/seeders/GoldenScenarioSeeder.php`, `docs/release/verification-report.md`
- Test: all `tests/Unit`, `tests/Feature`, and `tests/Browser` suites

**Interfaces:**
- Consumes: the complete P0/P1 system from DYX-002–020.
- Produces: repeatable evidence for the end-to-end promise and a release decision based on tests, accessibility, ownership, analytics formulas, and performance expectations.

- [ ] **Step 1: Write the failing golden flow** — sign in, create `PlanOps`/`PLAN`, create `PLAN-1`, create two subtasks, move the parent Not Started → In Progress → In Review, mark one subtask Done, mark the parent Done, verify activity/progress/dashboard/Created vs Completed, reopen the parent, and verify the reopen event plus reduced current progress.
- [ ] **Step 2: Run `npx playwright test tests/Browser/golden-planops-flow.spec.js`** — expected: FAIL until every core screen and mutation is connected.
- [ ] **Step 3: Implement only integration fixes discovered by the flow** — keep fixes inside existing domain boundaries, add focused regression tests for each failure, and do not add deferred P2 entities or automatic behavior.
- [ ] **Step 4: Run the complete verification suite** — `php artisan test --parallel`, `npm run build`, `npx playwright test tests/Browser`, and the documented production smoke test; expected: PASS with no critical accessibility failures.
- [ ] **Step 5: Complete the verification report** — record commands, results, metric definitions/data sources, timezone behavior, denominator rules, no-data behavior, accessibility checks, and known non-goals.
- [ ] **Step 6: Commit** — `git add tests database docs/release && git commit -m "test: verify PlanOps golden workflow"`.

## Spec Coverage Self-Review

| Specification area | Covered by |
|---|---|
| Product truth, non-goals, user stories, vocabulary | DYX-001, global constraints, DYX-021 |
| Preferences, timezone, theme, density | DYX-002, DYX-015, DYX-018 |
| Projects, keys, status, archive | DYX-005, DYX-011 |
| Tasks, numbering, metadata, priority, due dates, deletion | DYX-006, DYX-007 |
| Labels and ownership | DYX-003, DYX-007 |
| One-level subtasks and progress formulas | DYX-008, DYX-021 |
| Fixed statuses, timestamps, reopen | DYX-009, DYX-016, DYX-017, DYX-021 |
| Activity event schema, recorder, feeds, timeline | DYX-004, DYX-010 |
| Board, non-drag controls, reordering | DYX-012, DYX-018 |
| Project list/overview and task list | DYX-011, DYX-013 |
| My Work filters/sorting | DYX-013 |
| Search | DYX-014 |
| Dashboard periods, KPIs, charts, no-data semantics | DYX-015, DYX-016 |
| Analytics throughput/workflow/projects/activity | DYX-017 |
| Attention/stale indicators | DYX-019 |
| Accessibility, responsive layout, reduced motion | DYX-012, DYX-018, DYX-021 |
| Schema, indexes, transactions, time semantics | DYX-003, DYX-006, DYX-009, DYX-015, DYX-020 |
| Laravel actions/query services/routes/validation/policies | DYX-001, DYX-005–017, DYX-020 |
| Testing strategy and golden scenario | Every task’s tests, DYX-020, DYX-021 |
| P1 export | DYX-019 |
| Explicitly deferred P2 features | Global constraints; no task creates them |

### Placeholder and consistency checks

- No task depends on an undefined `DailyPlan`, timer, `WorkSession`, team, assignee, sprint, epic, custom workflow, dependency graph, WebSocket, or AI subsystem.
- The same enum names, route names, Action names, metric formulas, and task-key rules are used throughout the plan.
- Every task has a testable deliverable and a verification command; every domain mutation names its transaction/activity behavior.
- Every displayed metric is required to carry data-source, current-vs-period, subtask inclusion, reopen, timezone, denominator, and no-data semantics into its result object or UI contract.
- The first unblocked action is **DYX-001, Step 1: record the verified implementation baseline in `docs/architecture/stack.md`**.
