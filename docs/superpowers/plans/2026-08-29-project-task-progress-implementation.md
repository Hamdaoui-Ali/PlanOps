# Project Tasks and Derived Progress Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a project overview with project-owned tasks and derive 0–100% project progress from completed eligible top-level tasks.

**Architecture:** Reuse the existing `Project` → `Task` relationship, task creation action, owner-scoped route model binding, and aggregate progress query. Add a project overview query/controller view, a task status transition boundary, and accessible Blade controls; keep progress derived in the domain model/query rather than duplicating formulas in templates.

**Tech Stack:** Laravel 13, PHP 8.3 target, Eloquent, Blade, Pest, Vite/Tailwind CSS, existing PlanOps console styles.

**Spec:** `docs/superpowers/specs/2026-08-29-project-task-progress-design.md`

## Global Constraints

- Project progress uses completed eligible top-level tasks divided by eligible top-level tasks.
- Eligible tasks are top-level tasks whose status is not `CANCELLED`.
- Subtasks never add weight to the project percentage.
- Project lifecycle status remains manual and never changes automatically from task completion.
- No manually editable project-progress field is added.
- Task lists exclude soft-deleted tasks.
- Every project and task result is scoped to the authenticated owner; foreign resources resolve as unavailable.
- Every core action has visible text, keyboard-operable controls, and a non-color-only state indicator.
- Report PHP/Pest status honestly if the local PHP executable remains unavailable.

---

## File map

### Create

- `app/Domain/Projects/Queries/ProjectOverviewQuery.php` — loads one owned project with ordered non-deleted tasks and progress-related counts.
- `app/Domain/Tasks/Actions/ChangeTaskStatus.php` — validates ownership, records status timestamps/activity, and persists one status transition.
- `app/Http/Requests/ChangeTaskStatusRequest.php` — authorizes and validates status changes from the HTTP boundary.
- `resources/views/pages/projects/show.blade.php` — project overview, derived progress summary, task list, and empty state.
- `tests/Feature/Projects/ProjectOverviewTest.php` — overview rendering, ownership, task list, and progress display contract.
- `tests/Feature/Tasks/ChangeTaskStatusTest.php` — task status transition and progress update contract.
- `tests/Unit/Domain/Projects/ProjectProgressTest.php` — top-level, cancelled-task, subtask, and percentage calculation contract.

### Modify

- `routes/web.php` — add `GET /projects/{project}` and `POST /tasks/{task}/status`.
- `app/Http/Controllers/ProjectController.php` — add `show()` and inject `ProjectOverviewQuery`.
- `app/Http/Controllers/TaskController.php` — redirect successful creation to the project overview and add `changeStatus()`.
- `resources/views/pages/projects/index.blade.php` — replace duplicate `Scope progress` with `Tasks`, keep one `Progress` column, and link projects to the overview.
- `resources/views/pages/tasks/create.blade.php` — make cancel and success navigation return to the project overview.
- `resources/views/components/tasks/quick-create.blade.php` — point cancel back to the project overview.
- `app/Domain/Projects/Models/Project.php` — preserve and, if needed, centralize the top-level eligible progress calculation used by the overview.
- `tests/Feature/Tasks/CreateTaskTest.php` — update valid-creation redirect assertions to `projects.show`.
- `tests/Feature/Projects/ProjectManagementTest.php` — assert the Projects index uses the overview route and the single progress contract.

## Task 1: Add the project overview read model and route

**Files:**

- Create: `app/Domain/Projects/Queries/ProjectOverviewQuery.php`
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php`
- Create: `resources/views/pages/projects/show.blade.php`
- Test: `tests/Feature/Projects/ProjectOverviewTest.php`

**Interfaces:**

- Consumes: owner-scoped `Project $project`, `Project::tasks()`, `Project::progressCounts()`, and `Project::progressPercent()`.
- Produces: `ProjectOverviewQuery::for(User|int $owner, Project $project): Project` and named route `projects.show`.

- [ ] **Step 1: Write failing overview tests**

Add tests with the existing Pest style:

```php
test('an owner can view a project overview with its non-deleted tasks', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Plan the release']);

    $response = $this->actingAs($owner)->get(route('projects.show', $project));

    $response->assertOk()
        ->assertSee('Plan the release')
        ->assertSee('PLAN-1')
        ->assertSee('0%')
        ->assertSee('0 of 1 tasks done');
});

test('a project overview excludes soft deleted tasks and foreign projects', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $deleted = Task::factory()->forProject($project)->create();
    $deleted->delete();
    $foreignProject = Project::factory()->for($other)->create();

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee($deleted->title);

    $this->actingAs($owner)->get(route('projects.show', $foreignProject))
        ->assertNotFound();
});
```

- [ ] **Step 2: Run the focused test to verify the route is missing**

Run:

```powershell
php artisan test tests/Feature/Projects/ProjectOverviewTest.php
```

Expected: the command cannot execute if PHP is still unavailable; otherwise the new route/view assertions fail because `projects.show` is not defined.

- [ ] **Step 3: Implement the overview query and controller route**

Add the query with this contract:

```php
public function for(User|int $owner, Project $project): Project
{
    $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

    return Project::query()
        ->ownedBy($ownerId)
        ->whereKey($project->getKey())
        ->with(['tasks' => fn (HasMany $tasks) => $tasks
            ->whereNull('parent_task_id')
            ->orderByRaw("CASE WHEN status = ? THEN 1 ELSE 0 END", [TaskStatus::DONE->value])
            ->orderBy('position')
            ->orderBy('number')])
        ->withCount([
            'tasks as eligible_task_count' => fn (Builder $tasks): Builder => $tasks
                ->whereNull('parent_task_id')
                ->where('status', '!=', TaskStatus::CANCELLED->value),
            'tasks as completed_task_count' => fn (Builder $tasks): Builder => $tasks
                ->whereNull('parent_task_id')
                ->where('status', TaskStatus::DONE->value),
        ])
        ->firstOrFail();
}
```

Add `ProjectController::show(Project $project, ProjectOverviewQuery $overview): View` and return `pages.projects.show` with the project and `TaskStatus::cases()`.

Add before the parameterized project routes:

```php
Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
```

The existing project binding remains the owner boundary for this route. The
overview query must still scope the final lookup to the authenticated owner so
direct controller calls cannot bypass the boundary.

- [ ] **Step 4: Build the accessible overview view**

Render one progress summary and a task table. The progress bar must use:

```blade
@php($progress = min(max((float) $project->progress_percent, 0), 100))
<div role="progressbar"
     aria-label="{{ $project->name }} progress"
     aria-valuemin="0"
     aria-valuemax="100"
     aria-valuenow="{{ $progress }}">
    <span style="width: {{ $progress }}%"></span>
</div>
```

Show `{{ $project->completed_task_count }} of {{ $project->eligible_task_count }} tasks done`, the percentage, and the explanatory copy: `Progress uses completed top-level tasks. Cancelled tasks and subtasks are excluded.` For no eligible tasks, show `0%` and `No active scope`.

Each task row must show task key, title, status text, priority, due date, and a status form/select with a submit button labelled `Save status`. Include `New task` linking to `projects.tasks.create` and an empty state matching the spec.

- [ ] **Step 5: Run focused tests and static checks**

Run:

```powershell
php artisan test tests/Feature/Projects/ProjectOverviewTest.php
git diff --check
```

Expected: focused tests pass when PHP is installed; otherwise report the missing executable. Commit:

```powershell
git add app/Domain/Projects/Queries/ProjectOverviewQuery.php app/Http/Controllers/ProjectController.php routes/web.php resources/views/pages/projects/show.blade.php tests/Feature/Projects/ProjectOverviewTest.php
git commit -m "feat: add project task overview"
```

## Task 2: Make task creation return to the project overview

**Files:**

- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `resources/views/pages/tasks/create.blade.php`
- Modify: `resources/views/components/tasks/quick-create.blade.php`
- Modify: `tests/Feature/Tasks/CreateTaskTest.php`

**Interfaces:**

- Consumes: existing `CreateTask::handle(User $user, Project $project, array $attributes): Task`.
- Produces: successful `POST projects.tasks.store` redirects to `projects.show` with the display-key flash message.

- [ ] **Step 1: Change the existing feature assertions first**

Replace valid creation expectations with:

```php
$response->assertRedirect(route('projects.show', $project, absolute: false))
    ->assertSessionHas('status', 'PLAN-1 created.');
```

- [ ] **Step 2: Run the focused test and confirm the old redirect fails**

Run:

```powershell
php artisan test tests/Feature/Tasks/CreateTaskTest.php --filter='create route'
```

Expected: failure while the controller still redirects to `projects.tasks.create`.

- [ ] **Step 3: Update the controller and cancel links**

Change the successful return to:

```php
return to_route('projects.show', $project)
    ->with('status', $keys->displayKey($task).' created.');
```

Point both task-form cancel links to `route('projects.show', $project)`.

- [ ] **Step 4: Run the task creation tests**

Run:

```powershell
php artisan test tests/Feature/Tasks/CreateTaskTest.php
```

Expected: all task creation tests pass when PHP is installed. Commit:

```powershell
git add app/Http/Controllers/TaskController.php resources/views/pages/tasks/create.blade.php resources/views/components/tasks/quick-create.blade.php tests/Feature/Tasks/CreateTaskTest.php
git commit -m "feat: return to project after task creation"
```

## Task 3: Add an explicit task status transition for progress updates

**Files:**

- Create: `app/Domain/Tasks/Actions/ChangeTaskStatus.php`
- Create: `app/Http/Requests/ChangeTaskStatusRequest.php`
- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/pages/projects/show.blade.php`
- Create: `tests/Feature/Tasks/ChangeTaskStatusTest.php`

**Interfaces:**

- Consumes: `TaskStatus`, `TaskPolicy`, `TaskActivityRecorder`, and the owner-scoped `Task` model.
- Produces: `ChangeTaskStatus::handle(User $user, Task $task, TaskStatus|string $status): Task` and named route `tasks.status`.

- [ ] **Step 1: Write failing status and progress tests**

Add tests with these assertions:

```php
test('changing a top level task to done updates project progress', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create(['status' => TaskStatus::NOT_STARTED]);

    $response = $this->actingAs($owner)->post(route('tasks.status', $task), [
        'status' => TaskStatus::DONE->value,
    ]);

    $response->assertRedirect(route('projects.show', $project, absolute: false));
    expect($task->fresh()->status)->toBe(TaskStatus::DONE)
        ->and($project->fresh()->progress_percent)->toBe(100);
});

test('subtasks and cancelled top level tasks do not change project progress', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $done = Task::factory()->forProject($project)->done()->create();
    Task::factory()->forProject($project)->withParent($done)->create(['status' => TaskStatus::NOT_STARTED]);
    Task::factory()->forProject($project)->cancelled()->create();

    expect($project->fresh()->progress_percent)->toBe(100);
});
```

- [ ] **Step 2: Run the focused test to verify the transition boundary is missing**

Run:

```powershell
php artisan test tests/Feature/Tasks/ChangeTaskStatusTest.php
```

Expected: missing route/action failure, or PHP-unavailable failure.

- [ ] **Step 3: Implement the request and action**

The request rules are:

```php
return [
    'status' => ['required', 'string', Rule::in(array_column(TaskStatus::cases(), 'value'))],
];
```

The action must authorize `update` for the user/task, lock the owned task, no-op when the status is unchanged, and otherwise update `status`, `status_changed_at`, `first_started_at` when first entering `IN_PROGRESS`, `completed_at` when entering `DONE`, and clear `completed_at` when leaving `DONE`. Record one existing task status activity with old and new enum values inside the same transaction.

Add an owner-scoped implicit binding callback for `task` in `routes/web.php`:

```php
Route::bind('task', function (string $value): Task {
    return Task::query()->ownedBy(request()->user())->findOrFail($value);
});
```

Import `App\Domain\Tasks\Models\Task` alongside the existing project model.

- [ ] **Step 4: Add the route/controller and wire the overview form**

Add:

```php
Route::post('/tasks/{task}/status', [TaskController::class, 'changeStatus'])
    ->name('tasks.status');
```

The controller calls the action and redirects to `projects.show` using `$task->project_id`. The overview form posts the selected status to `tasks.status`, includes CSRF protection, preserves the task key/title, and has a text submit control.

- [ ] **Step 5: Run status, progress, and activity tests**

Run:

```powershell
php artisan test tests/Feature/Tasks/ChangeTaskStatusTest.php tests/Unit/Domain/Projects/ProjectProgressTest.php
git diff --check
```

Expected: pass when PHP is installed; otherwise report the environment limitation. Commit:

```powershell
git add app/Domain/Tasks/Actions/ChangeTaskStatus.php app/Http/Requests/ChangeTaskStatusRequest.php app/Http/Controllers/TaskController.php routes/web.php resources/views/pages/projects/show.blade.php tests/Feature/Tasks/ChangeTaskStatusTest.php
git commit -m "feat: update task status from project overview"
```

## Task 4: Add focused progress-domain coverage

**Files:**

- Create: `tests/Unit/Domain/Projects/ProjectProgressTest.php`
- Modify: `app/Domain/Projects/Models/Project.php` only if a focused calculation defect is exposed.

**Interfaces:**

- Consumes: `Project::progressCounts()`, `Project::progressPercent()`, and the existing `TaskStatus` enum.
- Produces: executable proof of the top-level-task denominator and no-active-scope behavior.

- [ ] **Step 1: Write the unit tests**

Add these cases:

```php
test('project progress counts completed eligible top level tasks', function () {
    $project = Project::factory()->create();
    Task::factory()->forProject($project)->done()->create();
    Task::factory()->forProject($project)->create(['status' => TaskStatus::IN_PROGRESS]);
    Task::factory()->forProject($project)->cancelled()->create();

    expect($project->fresh()->progressPercent())->toBe(50);
});

test('subtasks do not change project progress', function () {
    $project = Project::factory()->create();
    $parent = Task::factory()->forProject($project)->create(['status' => TaskStatus::IN_PROGRESS]);
    Task::factory()->forProject($project)->withParent($parent)->done()->create();

    expect($project->fresh()->progressPercent())->toBe(0);
});

test('a project without eligible tasks reports zero progress', function () {
    $project = Project::factory()->create();

    expect($project->progressCounts())->toBe([
        'eligible_task_count' => 0,
        'completed_task_count' => 0,
    ])->and($project->progressPercent())->toBe(0);
});
```

- [ ] **Step 2: Run the unit tests before changing production code**

Run:

```powershell
php artisan test tests/Unit/Domain/Projects/ProjectProgressTest.php
```

Expected: the tests pass if the existing progress model already matches the
approved rule; otherwise the failure identifies the exact calculation to fix.

- [ ] **Step 3: Make only the minimal domain correction if required**

Keep the existing `Project::progressCounts()` and `progressPercent()` public
contracts. Do not introduce a stored percentage or count subtasks. If a
correction is necessary, keep the eligible predicate identical in the model
and `ProjectIndexQuery`:

```php
$tasks->whereNull('parent_task_id')
    ->where('status', '!=', TaskStatus::CANCELLED->value);
```

- [ ] **Step 4: Re-run the unit tests and commit**

Run `php artisan test tests/Unit/Domain/Projects/ProjectProgressTest.php` and
then commit:

```powershell
git add tests/Unit/Domain/Projects/ProjectProgressTest.php app/Domain/Projects/Models/Project.php
git commit -m "test: define top level project progress"
```

## Task 5: Clarify progress on the Projects index and complete regression coverage

**Files:**

- Modify: `resources/views/pages/projects/index.blade.php`
- Modify: `tests/Feature/Projects/ProjectManagementTest.php`
- Modify: `app/Domain/Projects/Queries/ProjectIndexQuery.php` only if aggregate aliases need correction.

**Interfaces:**

- Consumes: `eligible_task_count`, `completed_task_count`, and `progress_percent` from the existing project query/model contract.
- Produces: one unambiguous task-count column and one derived progress column on `/projects`.

- [ ] **Step 1: Add the index regression assertions**

Extend the existing project management feature test to assert the response contains `Tasks`, `Progress`, and the explanatory copy, and does not contain the table header `Scope progress`.

- [ ] **Step 2: Update the table markup**

Replace the header with:

```blade
<th scope="col">Tasks</th>
<th scope="col">Progress</th>
```

Render `{{ $project->completed_task_count }} of {{ $project->eligible_task_count }} done` in the Tasks cell. Render the percentage and progress bar only once in the Progress cell. For zero eligible tasks, display `0%` and `No active scope`. Add helper text outside the table explaining the top-level-task rule.

Change the project identity and `Open project` links to `route('projects.show', $project)`. Keep the edit form reachable through a clearly labelled `Edit project` action on the overview.

- [ ] **Step 3: Run the complete relevant suite**

Run:

```powershell
php artisan test tests/Feature/Projects tests/Feature/Tasks tests/Unit/Domain/Projects
npm run build
git diff --check
```

Expected: PHP/Pest tests pass when PHP is installed, frontend build passes, and diff check reports no whitespace errors. Commit:

```powershell
git add resources/views/pages/projects/index.blade.php tests/Feature/Projects/ProjectManagementTest.php app/Domain/Projects/Queries/ProjectIndexQuery.php
git commit -m "feat: clarify task based project progress"
```

## Final verification and handoff

- [ ] Run `npm run build` from a clean worktree.
- [ ] Run `git diff --check HEAD~5..HEAD` and inspect the final diff.
- [ ] Run `git status --short --branch` and confirm only intentional commits are present.
- [ ] If PHP is available, run the relevant Pest suite and record the exact result.
- [ ] If PHP is unavailable, state that limitation explicitly and do not claim Laravel tests passed.
- [ ] Confirm the branch contains the five feature commits plus the already committed design and plan documents before any push is requested.
