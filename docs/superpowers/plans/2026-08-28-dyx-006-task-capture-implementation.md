# DYX-006 Task Capture and Atomic Numbering Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task with review checkpoints. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add owner-safe task capture with atomic project-local numbering, stable display keys, creation activity, and a compact project-scoped Blade form.

**Architecture:** Keep task creation in one explicit `CreateTask` Action. `TaskPolicy` and `StoreTaskRequest` protect the HTTP boundary, `TaskKeyQuery` owns the derived `{project.key}-{task.number}` display key, and the controller only composes validated input with those units. Extend the existing option 3 authenticated console with one short form and a reusable quick-create partial; do not introduce task editing, labels, boards, or list behavior in this slice.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, PostgreSQL in production, Pest, Blade, Tailwind CSS, Alpine.js, Vite, and the existing Phosphor regular icon dependency.

**Spec:** `docs/superpowers/specs/2026-08-28-dyx-006-task-capture-design.md`

## Global Constraints

- Scope every user-owned query, relationship, action, policy, and search by `user_id`.
- Task defaults are `NOT_STARTED` and `MEDIUM`.
- Task display identity is `{PROJECT_KEY}-{TASK_NUMBER}` and is derived, not stored redundantly.
- Allocate numbers by beginning a database transaction, locking the project row for update, reading `next_task_number`, creating the task, incrementing the counter, recording `TASK_CREATED`, and committing.
- Numbers are never reused, including after soft deletion; a failed transaction rolls back the task, counter, and activity together.
- A parent must belong to the authenticated user and route project and must itself be top-level; foreign and nested parents cannot be selected or created.
- The route-bound project is owner-scoped and a foreign project resolves as `404`.
- Do not introduce labels, general task editing, automatic state changes, task movement between projects, new tables, organizations, roles, queues, schedulers, WebSockets, or external search.
- Activity payloads contain stable key/status/priority context and do not copy task titles or descriptions.
- Visible task UI text remains code-native, labelled, keyboard-operable, responsive, and continuous with the accepted Product Design option 3 console.
- Use Figtree, the existing dark console tokens, Phosphor regular icons, visible focus rings, native disclosure semantics, and reduced-motion behavior already established in `resources/css/app.css`.
- Write tests before production code. Do not report PHP tests as passing when `php` is unavailable on the host.

---

### Task 1: Write the failing task-capture contract

**Files:**

- Create: `tests/Unit/Domain/Tasks/TaskKeyTest.php`
- Create: `tests/Feature/Tasks/CreateTaskTest.php`
- Create: `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`

**Interfaces:**

- Consumes: existing `Project`, `Task`, `TaskActivity`, `TaskStatus`, `TaskPriority`, `User`, and `RefreshDatabase` conventions.
- Produces: the executable contract for `TaskKeyQuery`, `TaskPolicy`, `CreateTask`, `StoreTaskRequest`, `TaskController`, routes, and the quick-create UI.

- [ ] **Step 1: Write the display-key unit tests first.**

  Add tests that create project `PLAN` and task number `1`, then assert:

  ```php
  expect((new TaskKeyQuery)->displayKey($task))->toBe('PLAN-1');
  expect($task->getRawOriginal('display_key'))->toBeNull();
  ```

  Also cover a task whose project relation is not preloaded and a task with no valid project identity; the formatter must load the project key when possible and throw a clear `LogicException` instead of emitting a malformed key.

- [ ] **Step 2: Write feature tests for creation and ownership.**

  In `CreateTaskTest.php`, cover these named behaviors with real Eloquent records:

  - the owner can create a task with only a title and receives number `1`, status `NOT_STARTED`, priority `MEDIUM`, and derived key `PLAN-1`;
  - optional `description`, `status`, `priority`, and `due_on` values persist;
  - `status_changed_at` is populated at creation;
  - exactly one `TASK_CREATED` activity is created with the owner/project/task context and stable key/status/priority values, without title or description text;
  - a second task receives number `2`;
  - a soft-deleted first task does not allow number `1` to be reused;
  - a foreign project, foreign parent, cross-project parent, nested parent, and self-parent are rejected without creating a task;
  - the GET creation route renders `Project`, `Title`, `More task details`, `Status`, `Priority`, and `Due date` labels;
  - a valid POST redirects to the named create route and contains `PLAN-1 created.`;
  - an invalid POST preserves the title and returns a field-level error;
  - a foreign project URL returns `404` and does not render its project or parent options.

- [ ] **Step 3: Write the number-allocation concurrency contract.**

  In `TaskNumberConcurrencyTest.php`, use the real project database connection and the real `CreateTask` Action, not a mocked counter. Run two or more creation attempts against one project, assert successful task numbers are distinct and sequential, assert `next_task_number` advances by the number of successful creations, and assert no duplicate `(project_id, number)` row exists. When the test driver is SQLite `:memory:`, keep the same observable repeated-allocation assertions because separate worker connections cannot share that database; a PostgreSQL run must exercise the row-lock path.

- [ ] **Step 4: Run the contract tests and verify the red state.**

  Run:

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/TaskKeyTest.php tests/Feature/Tasks/CreateTaskTest.php tests/Feature/Tasks/TaskNumberConcurrencyTest.php
  ```

  Expected result on a PHP-enabled host: failure because `TaskKeyQuery`, `CreateTask`, the policy, routes, and views do not exist. On the current host, record the exact executable failure because `php` is not installed; do not replace it with a false pass.

- [ ] **Step 5: Commit the red contract.**

  ```powershell
  git add -- tests/Unit/Domain/Tasks/TaskKeyTest.php tests/Feature/Tasks/CreateTaskTest.php tests/Feature/Tasks/TaskNumberConcurrencyTest.php
  git commit -m "test: define task capture contract"
  ```

---

### Task 2: Implement the display key and creation policy

**Files:**

- Create: `app/Domain/Tasks/Queries/TaskKeyQuery.php`
- Create: `app/Policies/TaskPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Domain/Tasks/TaskKeyTest.php`

**Interfaces:**

- Consumes: `Task` and `Project` relations and the existing `ProjectPolicy` registration pattern.
- Produces: `TaskKeyQuery::displayKey(Task $task): string` and `TaskPolicy::create(User $user, Project $project): bool`.

- [ ] **Step 1: Implement `TaskKeyQuery` minimally.**

  Use the loaded project relation when available and otherwise load only the project relation needed for the key. Reject an unsaved task, missing project, missing project key, or number below `1` with `LogicException`. Return the uppercase project key and integer task number in this exact shape:

  ```php
  return strtoupper($project->key).'-'.(int) $task->number;
  ```

  Do not add a `display_key` database column or a second formatter in a controller/view.

- [ ] **Step 2: Implement and register `TaskPolicy`.**

  ```php
  public function create(User $user, Project $project): bool
  {
      return (string) $user->getKey() === (string) $project->user_id;
  }
  ```

  Register `Task::class` with `TaskPolicy::class` in `AppServiceProvider::boot()` beside the existing project policy. Keep the comparison string-safe so the policy behaves consistently across PostgreSQL integer hydration and other drivers.

- [ ] **Step 3: Run the unit and policy coverage.**

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/TaskKeyTest.php tests/Feature/Tasks/CreateTaskTest.php --filter="key|policy"
  ```

  Expected result: the key and policy assertions pass; the remaining creation assertions remain red until Task 3 and Task 4.

- [ ] **Step 4: Commit the focused domain boundary.**

  ```powershell
  git add -- app/Domain/Tasks/Queries/TaskKeyQuery.php app/Policies/TaskPolicy.php app/Providers/AppServiceProvider.php
  git commit -m "feat: add task display keys and policy"
  ```

---

### Task 3: Implement atomic task creation and activity recording

**Files:**

- Create: `app/Domain/Tasks/Actions/CreateTask.php`
- Modify: `app/Domain/Tasks/Models/Task.php` only if a creation-specific cast or helper is required by the tests
- Test: `tests/Feature/Tasks/CreateTaskTest.php`
- Test: `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`

**Interfaces:**

- Consumes: `User`, owned `Project`, `Task`, `TaskStatus`, `TaskPriority`, `TaskActivityRecorder`, `TaskActivityType`, and `TaskKeyQuery`.
- Produces: `CreateTask::handle(User $user, Project $project, array $attributes): Task`.

- [ ] **Step 1: Normalize and validate Action input.**

  Normalize `title` and `description` with `trim`; convert empty optional strings to `null`; resolve optional status and priority values; default missing status and priority to `TaskStatus::NOT_STARTED` and `TaskPriority::MEDIUM`. Validate the same domain rules as the request so direct Action callers cannot bypass them:

  ```php
  Validator::make($values, [
      'title' => ['required', 'string', 'max:300'],
      'description' => ['nullable', 'string'],
      'status' => ['required', Rule::in(array_column(TaskStatus::cases(), 'value'))],
      'priority' => ['required', Rule::in(array_column(TaskPriority::cases(), 'value'))],
      'due_on' => ['nullable', 'date'],
      'parent_task_id' => ['nullable', 'integer'],
  ])->validate();
  ```

- [ ] **Step 2: Authorize before opening the transaction.**

  Call `Gate::forUser($user)->authorize('create', [Task::class, $project])`. Compare the project owner again inside the Action boundary when validating parent/project ownership; a direct caller must not create a task for another user.

- [ ] **Step 3: Allocate the number and create the task in one transaction.**

  Implement the core sequence with the existing project counter:

  ```php
  return DB::transaction(function () use ($user, $project, $values): Task {
      $lockedProject = Project::query()
          ->whereKey($project->getKey())
          ->where('user_id', $user->getKey())
          ->lockForUpdate()
          ->firstOrFail();

      $number = (int) $lockedProject->next_task_number;
      if ($number < 1) {
          throw new LogicException('Project task numbering must start at 1.');
      }

      $parent = null;
      $parentId = $values['parent_task_id'] ?? null;
      if ($parentId !== null) {
          $parent = Task::query()
              ->whereKey($parentId)
              ->where('user_id', $user->getKey())
              ->where('project_id', $lockedProject->getKey())
              ->whereNull('parent_task_id')
              ->first();

          if ($parent === null) {
              throw ValidationException::withMessages([
                  'parent_task_id' => 'The selected parent task is unavailable.',
              ]);
          }
      }

      $task = Task::query()->create([
          ...$values,
          'user_id' => $user->getKey(),
          'project_id' => $lockedProject->getKey(),
          'number' => $number,
          'parent_task_id' => $parent?->getKey(),
          'status_changed_at' => now(),
      ]);

      $lockedProject->forceFill(['next_task_number' => $number + 1])->save();
      $key = (new TaskKeyQuery)->displayKey($task->load('project'));
      app(TaskActivityRecorder::class)->record(
          $task,
          TaskActivityType::TASK_CREATED,
          null,
          null,
          [
              'display_key' => $key,
              'status' => $task->status,
              'priority' => $task->priority,
          ],
      );

      return $task->load('project');
  });
  ```

  The parent query must be the only source of the stored `parent_task_id`; it
  rejects foreign, cross-project, nested, and self-parent values before task
  creation. The activity `new_value` must contain
  the derived display key, status, and priority; it must not include title or
  description. Do not decrement the counter after a task is soft-deleted.

- [ ] **Step 4: Run domain and feature coverage.**

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/TaskKeyTest.php tests/Feature/Tasks/CreateTaskTest.php tests/Feature/Tasks/TaskNumberConcurrencyTest.php
  ```

  Expected result on a PHP-enabled host: defaults, optional fields, activity, number stability, parent validation, and repeated/concurrent allocation assertions pass. If a test fails, fix the Action or test setup; do not relax an ownership or transaction assertion.

- [ ] **Step 5: Commit the transaction boundary.**

  ```powershell
  git add -- app/Domain/Tasks/Actions/CreateTask.php app/Domain/Tasks/Models/Task.php
  git commit -m "feat: add atomic task creation"
  ```

---

### Task 4: Add the owner-safe HTTP boundary

**Files:**

- Create: `app/Http/Controllers/TaskController.php`
- Create: `app/Http/Requests/StoreTaskRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tasks/CreateTaskTest.php`

**Interfaces:**

- Consumes: the existing owner-scoped `project` route binding, `CreateTask`, `TaskKeyQuery`, `TaskStatus`, and `TaskPriority`.
- Produces: `GET /projects/{project}/tasks/create` named `projects.tasks.create` and `POST /projects/{project}/tasks` named `projects.tasks.store`.

- [ ] **Step 1: Implement `StoreTaskRequest` as the HTTP validator.**

  `prepareForValidation()` trims `title` and `description`. `rules()` validates `title`, `description`, optional enum values, optional date, and an integer `parent_task_id`; it does not use an unscoped `exists:tasks,id` rule. `authorize()` checks the route-bound project with:

  ```php
  $project = $this->route('project');

  return $project instanceof Project
      && ($this->user()?->can('create', [Task::class, $project]) ?? false);
  ```

  Add specific messages for required title, title length, invalid status, invalid priority, invalid date, and unavailable parent.

- [ ] **Step 2: Implement the controller and parent options.**

  `create(Project $project)` queries only non-deleted, top-level tasks from the same project and owner, orders them by number, and maps each option through `TaskKeyQuery::displayKey()`. It passes the project, parent options, `TaskStatus::cases()`, and `TaskPriority::cases()` to `pages.tasks.create`.

  `store(StoreTaskRequest $request, Project $project, CreateTask $create, TaskKeyQuery $keys)` passes only `$request->validated()` to the Action, computes the new display key once, and redirects to `projects.tasks.create` with a status message:

  ```php
  return to_route('projects.tasks.create', $project)
      ->with('status', $keys->displayKey($task).' created.');
  ```

- [ ] **Step 3: Add explicit routes without widening project access.**

  Add the two routes inside the existing authenticated group, after the literal project-create route and alongside the project lifecycle routes:

  ```php
  Route::get('/projects/{project}/tasks/create', [TaskController::class, 'create'])
      ->name('projects.tasks.create');
  Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])
      ->name('projects.tasks.store');
  ```

  Keep the existing owner-scoped `Route::bind('project', ...)`; do not add a global task binding or a task update route in DYX-006.

- [ ] **Step 4: Run the HTTP tests.**

  ```powershell
  php artisan test tests/Feature/Tasks/CreateTaskTest.php
  php artisan route:list --path=projects
  ```

  Expected result: GET/POST task creation is green, foreign project URLs are `404`, and route output contains only the two new explicit task-capture routes plus the existing project routes.

- [ ] **Step 5: Commit the HTTP boundary.**

  ```powershell
  git add -- app/Http/Controllers/TaskController.php app/Http/Requests/StoreTaskRequest.php routes/web.php
  git commit -m "feat: add task creation routes"
  ```

---

### Task 5: Build the compact task-capture screen

**Files:**

- Create: `resources/views/pages/tasks/create.blade.php`
- Create: `resources/views/components/tasks/quick-create.blade.php`
- Modify: `resources/views/pages/projects/edit.blade.php` to expose a reachable `Create task` action
- Modify: `resources/css/app.css` for the task form and responsive details disclosure
- Test: `tests/Feature/Tasks/CreateTaskTest.php`

**Interfaces:**

- Consumes: the named task creation routes, project context, parent option display keys, enum case lists, session status, and existing `x-app-layout`/Phosphor styling.
- Produces: a short, keyboard-operable form that can create a task without forcing labels or later task-editing fields.

- [ ] **Step 1: Implement the reusable quick-create partial.**

  Keep `Project` and `Title` visible. Render project context as a labelled readonly control; do not trust a client-supplied project ID because the route owns that identity. Use `@csrf`, the named POST route, `old()` values, `x-input-error`, and the following code-native labels:

  ```text
  Project
  Title
  More task details
  Description
  Status
  Priority
  Due date
  Parent task
  Create task
  Cancel
  ```

  Use native `<details>` and `<summary>` for optional fields. Open the disclosure after a validation error so the user can see the failing optional field. Apply `NOT_STARTED` and `MEDIUM` as the selected defaults. Do not render label controls in this slice.

- [ ] **Step 2: Implement the page and success state.**

  `pages/tasks/create.blade.php` uses the existing authenticated shell, renders a `Create task` heading, exposes the status message as `role="status"`, and includes the quick-create partial. The primary action is `Create task`; `Cancel` returns to `projects.edit`. Keep the form in one bordered surface with the option 3 dark canvas, restrained dividers, 6–10px control radii, and no gradients/glass/glow.

- [ ] **Step 3: Make the entry point reachable from project editing.**

  Add a text-labelled `Create task` link to the existing project edit actions, pointing to `projects.tasks.create`. Preserve the existing project lifecycle forms and their owner-safe routes.

- [ ] **Step 4: Add responsive and accessibility styling.**

  Reuse the existing `--planops-*` variables and Figtree rules. Ensure controls are at least 40px high, labels remain associated, keyboard focus is visible, the disclosure works without JavaScript, the project context and actions remain readable at 390px width, and reduced-motion rules continue to apply. Use Phosphor icons only decoratively with `aria-hidden="true"` beside visible labels.

- [ ] **Step 5: Run the UI contract and build checks.**

  ```powershell
  php artisan test tests/Feature/Tasks/CreateTaskTest.php
  npm run build
  git diff --check
  ```

  Expected result: the task screen assertions and Vite build pass on an available runtime. With no PHP executable, record the PHP failure and rely only on the successful asset/static checks for this host.

- [ ] **Step 6: Commit the screen.**

  ```powershell
  git add -- resources/views/pages/tasks/create.blade.php resources/views/components/tasks/quick-create.blade.php resources/views/pages/projects/edit.blade.php resources/css/app.css
  git commit -m "feat: add compact task capture UI"
  ```

---

### Task 6: Verify, review, update the master plan, and synchronize DYX-006

**Files:**

- Modify: `docs/superpowers/plans/2026-08-20-planops-implementation.md:213-218`
- Review artifact: `.superpowers/sdd/2026-08-28-dyx-006-task-capture-implementation/`

**Interfaces:**

- Consumes: all DYX-006 commits and the accepted design/spec.
- Produces: verified task-capture behavior, a clean commit series on `main`, and a synchronized `origin/main`.

- [ ] **Step 1: Run the complete focused verification set.**

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/TaskKeyTest.php tests/Feature/Tasks/CreateTaskTest.php tests/Feature/Tasks/TaskNumberConcurrencyTest.php
  php artisan route:list --path=projects
  php artisan test --parallel
  npm run build
  git diff --check
  ```

  Record each command’s exit code. Do not mark PHP-dependent steps as passing when the executable is missing.

- [ ] **Step 2: Run rendered QA when the runtime exists.**

  The flow under test is: `/projects` → open a project → `Create task` → submit title-only task → see `PLAN-1 created.` → open the create screen again and see the next number available through the returned state. Check desktop `1440×1024` and mobile `390×844`, plus invalid-title and parent-validation states. Use the Browser/IAB path first; if it is unavailable, use the documented Playwright fallback and record `Browser plugin not available`. Inspect the accepted option 3 reference and the latest implementation screenshot together with `view_image` when a rendered screenshot can be captured.

  Keep a fidelity ledger with at least these comparison points: rail width and hierarchy, page title/action alignment, form container/border treatment, Figtree type scale and control typography, palette and focus color, icon family/optical size, spacing/radii, disclosure behavior, mobile stacking, and success/error copy. Fix any material visible mismatch before proceeding.

- [ ] **Step 3: Update only the DYX-006 checklist markers.**

  Mark Step 1, Step 3, Step 4, and Step 6 in the master plan as `[x]` after their work is evidenced. Keep Step 2 and Step 5 unchecked if PHP is unavailable, and add a short verification note immediately below DYX-006 stating that PHP tests/route listing/browser rendering still require a PHP-enabled environment. Do not mark DYX-007 through DYX-021.

- [ ] **Step 4: Perform the final local review.**

  Review the full range from the DYX-006 base through `HEAD` for owner scoping, transaction order, activity redaction, route ordering, form labels, responsive overflow, and accidental changes outside the file inventory. Confirm no `.superpowers/` artifacts, screenshots, credentials, or generated references are staged.

- [ ] **Step 5: Commit the master-plan bookkeeping.**

  ```powershell
  git add -- docs/superpowers/plans/2026-08-20-planops-implementation.md
  git commit -m "docs: record DYX-006 verification boundary"
  ```

- [ ] **Step 6: Push the reviewed commit series.**

  ```powershell
  git status -sb
  git rev-list --left-right --count origin/main...main
  git push origin main
  git status -sb
  ```

  Expected final state: local `main` and `origin/main` point to the same commit, with no tracked or untracked worktree changes.

## Self-review checklist

- **Spec coverage:** Task 1 covers defaults, derived keys, activity, number stability, parent safety, HTTP labels, and the required creation flow. Tasks 2–4 implement the domain and HTTP contracts. Task 5 implements the approved option 3 extension. Task 6 covers runtime/static/rendered verification and explicit PHP limitations.
- **Placeholder scan:** No unresolved placeholder or unspecified implementation step is required. Every production boundary has an exact file, signature, route, or visible copy contract.
- **Type/interface consistency:** `CreateTask::handle(User $user, Project $project, array $attributes): Task`, `TaskKeyQuery::displayKey(Task $task): string`, `TaskPolicy::create(User $user, Project $project): bool`, and the two named routes are used consistently across the tasks.
- **Scope check:** Labels and `UpdateTaskRequest` are explicitly deferred to DYX-007; no later task depends on a DYX-006 implementation that does not exist.
- **Visual continuity:** The form reuses the accepted option 3 console rather than inventing a new visual target or card family.
