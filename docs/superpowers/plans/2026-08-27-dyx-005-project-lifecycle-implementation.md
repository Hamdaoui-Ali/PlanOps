# DYX-005 Project Lifecycle and Projects Console Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task with review checkpoints.

**Goal:** Implement owner-safe project lifecycle behavior and a responsive Projects console faithful to the accepted Product Design option 3.

**Architecture:** Keep project rules in focused Actions, Requests, a Policy, and `ProjectIndexQuery`. Use server-rendered Laravel Blade with Alpine for the mobile menu, row focus, and archive confirmation. Extend the existing application shell and load Phosphor icons as a local dependency.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Pest, Blade, Tailwind CSS, Alpine.js, Vite, and Phosphor Icons for web.

**Spec:** `planops-complete-spec.md`, `docs/ui/screen-spec.md`, `docs/superpowers/specs/2026-08-27-dyx-005-project-lifecycle-design.md`

## Global Constraints

- Scope every user-owned query, relationship, action, policy, and search by `user_id`.
- Project status is manual: `PLANNED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, `CANCELLED`.
- Archive is separate from project status and is represented by `archived_at`.
- Project keys are 2–10 uppercase letters/numbers, unique per user, and immutable after the first task exists.
- Progress counts only non-cancelled top-level tasks; zero eligible tasks displays `0%` with `No active scope`.
- Core actions must work without drag-and-drop, color-only meaning, or mouse-only controls.
- Use canonical terminology `Project`; never render `Issue` or `Ticket`.
- Do not introduce organizations, roles, queues, schedulers, WebSockets, or new tables.
- Do not commit `.superpowers/`, screenshots, generated references, or runtime reports.

---

### Task 1: Define the failing project contract

**Files:**

- Create: `tests/Unit/Domain/Projects/ProjectKeyTest.php`
- Create: `tests/Feature/Projects/ProjectManagementTest.php`

**Interfaces:**

- Consumes: existing `Project`, `Task`, `TaskActivity`, `ProjectStatus`, and `User` models.
- Produces: the executable contract for Actions, Policy, Query, Requests, controller, routes, and views.

- [ ] **Step 1:** Write tests for valid creation, trimmed names, uppercase keys, invalid keys, duplicate keys per user, target date ordering, key lock before/after tasks, manual status, archive/restore preservation, index ownership/filtering/sorting/progress, HTTP validation, and cross-user 404 behavior.
- [ ] **Step 2:** Run `php artisan test tests/Unit/Domain/Projects/ProjectKeyTest.php tests/Feature/Projects/ProjectManagementTest.php`; expect failure because the new application units do not exist.
- [ ] **Step 3:** Commit with `git add -- tests/Unit/Domain/Projects/ProjectKeyTest.php tests/Feature/Projects/ProjectManagementTest.php && git commit -m "test: define project lifecycle contract"`.

### Task 2: Implement project domain behavior

**Files:**

- Create: `app/Domain/Projects/Actions/{CreateProject,UpdateProject,ChangeProjectStatus,ArchiveProject,RestoreProject}.php`
- Create: `app/Domain/Projects/Queries/ProjectIndexQuery.php`
- Create: `app/Policies/ProjectPolicy.php`
- Modify: `app/Domain/Projects/Models/Project.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**

- `CreateProject::handle(User $user, array $attributes): Project`
- `UpdateProject::handle(User $user, Project $project, array $attributes): Project`
- `ChangeProjectStatus::handle(User $user, Project $project, ProjectStatus|string $status): Project`
- `ArchiveProject::handle(User $user, Project $project): Project`
- `RestoreProject::handle(User $user, Project $project): Project`
- `ProjectIndexQuery::paginate(User|int $owner, array $filters = [], int $perPage = 50): LengthAwarePaginator`

- [ ] **Step 1:** Authorize each Action, normalize names/keys, validate domain invariants, lock keys after any task including soft-deleted tasks, keep status explicit, and make archive/restore idempotent without touching tasks/activity.
- [ ] **Step 2:** Add policy methods `viewAny`, `view`, `create`, `update`, `changeStatus`, `archive`, and `restore`; register the policy explicitly.
- [ ] **Step 3:** Add derived top-level task counts and `ProjectIndexQuery` filters for `search`, `status`, `archived`, and `target_date`, with deterministic `updated`, `name`, `progress`, `target_on`, and `created` sorting.
- [ ] **Step 4:** Run the domain tests; expected result is green for domain/query assertions while HTTP route assertions remain red until Task 3.
- [ ] **Step 5:** Commit with `git add -- app/Domain/Projects app/Policies/ProjectPolicy.php app/Providers/AppServiceProvider.php && git commit -m "feat: add project lifecycle actions"`.

### Task 3: Add HTTP boundaries and forms

**Files:**

- Create: `app/Http/Controllers/ProjectController.php`
- Create: `app/Http/Requests/{StoreProjectRequest,UpdateProjectRequest,ChangeProjectStatusRequest}.php`
- Modify: `routes/web.php`
- Create: `resources/views/pages/projects/{create,edit}.blade.php`

**Interfaces:**

- `GET /projects/create`, `POST /projects`, `GET /projects/{project}/edit`, `PATCH /projects/{project}`.
- `POST /projects/{project}/status`, `/archive`, and `/restore`.

- [ ] **Step 1:** Implement Requests with uppercase key preparation, per-user unique rules with update ignore, `after_or_equal:start_on`, field-level messages, and policy-aware `authorize()` methods.
- [ ] **Step 2:** Add owner-scoped `{project}` binding and controller methods that pass only validated data to Actions and return named-route redirects with status messages.
- [ ] **Step 3:** Build create/edit forms with explicit labels, field errors, preserved `old()` values, read-only key explanation after first task, separate status form, and archive/restore actions.
- [ ] **Step 4:** Run `php artisan test tests/Feature/Projects/ProjectManagementTest.php` and `php artisan route:list --path=projects`; expect all project HTTP tests green and only explicit project routes.
- [ ] **Step 5:** Commit with `git add -- app/Http/Controllers/ProjectController.php app/Http/Requests routes/web.php resources/views/pages/projects/create.blade.php resources/views/pages/projects/edit.blade.php && git commit -m "feat: add project lifecycle routes and forms"`.

### Task 4: Build the accepted Projects console

**Files:**

- Create: `resources/views/pages/projects/index.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/navigation.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Modify: `package.json`
- Modify: `package-lock.json`

**Interfaces:**

- `GET /projects` renders an owner-scoped project ledger with search, active/archive/status/target filters, sort, pagination, progress, and empty states.
- The shell exposes keyboard-operable navigation, visible focus, and a labeled mobile menu.

- [ ] **Step 1:** Run `npm install @phosphor-icons/web@2.1.2`, import its regular icon CSS, and use library icons with text labels instead of hand-drawn SVGs or glyphs.
- [ ] **Step 2:** Implement the accepted dark palette, 216px rail, `Projects` title, `New project`, `Find a project`, filter controls, row-based project table, lime focused row, text statuses, progress bars, and `Open project` links. Keep controls real links/forms.
- [ ] **Step 3:** Add mobile layout rules, stacked controls, list rows, focus-visible rings, and reduced-motion behavior.
- [ ] **Step 4:** Run `npm run build`; expected result is a clean Vite build.
- [ ] **Step 5:** Commit with `git add -- resources app package.json package-lock.json && git commit -m "feat: build projects console UI"`.

### Task 5: Verify, review, and synchronize DYX-005

**Files:**

- Modify: `docs/superpowers/plans/2026-08-20-planops-implementation.md:190-195`

- [ ] **Step 1:** Run `php artisan test tests/Unit/Domain/Projects tests/Feature/Projects`, `php artisan route:list --path=projects`, `npm run build`, and `git diff --check`.
- [ ] **Step 2:** Use the Browser plugin first, or Playwright with the reason recorded if Browser is unavailable. Check `/projects`, `/projects/create`, validation, search/filter/sort, keyboard row focus, edit, status change, archive confirmation, restore, desktop 1440×1024, and mobile 390×844.
- [ ] **Step 3:** Mark only the six DYX-005 checklist markers in the master plan as `[x]`; preserve DYX-006–DYX-021 and do not claim unavailable commands passed.
- [ ] **Step 4:** Commit with `git add -- docs/superpowers/plans/2026-08-20-planops-implementation.md && git commit -m "docs: mark DYX-005 project lifecycle complete"`.
- [ ] **Step 5:** Push the reviewed commits with `git push origin main`.
