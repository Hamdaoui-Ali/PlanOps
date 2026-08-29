# Task Editing and Collapsible Subtasks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make owned top-level tasks and direct subtasks visible and editable through a shared task detail interface with accessible collapsible subtasks.

**Architecture:** Reuse the existing `UpdateTask`, `ChangeTaskPriority`, `ChangeTaskDueDate`, `ChangeTaskStatus`, and `DeleteTask` actions. Add task-detail query/controller routes, then update the project overview query and Blade view to render direct children in collapsed regions controlled by small inline JavaScript. Do not add recursive nesting, stored progress, or a new client-side framework.

**Tech Stack:** Laravel 13, PHP 8.3 target, Eloquent, Blade, Pest, Vite/Tailwind CSS, Alpine.js already present in the application.

**Spec:** `docs/superpowers/specs/2026-08-29-task-editing-collapsible-subtasks-design.md`

## Global Constraints

- Top-level tasks and subtasks use the same editable task detail surface.
- Subtasks are direct children only and are collapsed by default in the project overview.
- Each task and subtask owns independent status, priority, and due-date values.
- Parent and child statuses never synchronize automatically.
- Project progress continues to use completed eligible top-level tasks only.
- Soft-deleted tasks and children are excluded from normal project/detail views.
- Foreign task URLs and mutations are unavailable through owner-scoped binding and authorization.
- Expand/collapse uses a real button with `aria-expanded` and `aria-controls`.
- No essential action is icon-only, hover-only, drag-only, or mouse-only.

---

## File map

### Create

- `app/Domain/Tasks/Queries/TaskDetailQuery.php` — loads an owned task with project, direct children, and activity.
- `resources/views/pages/tasks/show.blade.php` — shared editable task detail surface.
- `tests/Feature/Tasks/TaskDetailTest.php` — detail rendering, ownership, children, and mutation redirect contract.

### Modify

- `routes/web.php` — add owner-scoped task detail, update, priority, due-date, and delete routes.
- `app/Http/Controllers/TaskController.php` — add detail and mutation endpoints.
- `resources/views/pages/projects/show.blade.php` — add task edit links and collapsed direct-subtask regions.
- `app/Domain/Projects/Queries/ProjectOverviewQuery.php` — constrain direct children and expose child summaries.
- `resources/css/app.css` — style task-detail fields and indented collapsed children responsively.
- `tests/Feature/Tasks/TaskMetadataTest.php` — add HTTP tests for task edit/priority/due-date/delete endpoints.
- `tests/Feature/Projects/ProjectOverviewTest.php` — verify parent/child rendering and accessibility attributes.

## Task 1: Add the task detail read surface

**Files:**

- Create: `app/Domain/Tasks/Queries/TaskDetailQuery.php`
- Create: `resources/views/pages/tasks/show.blade.php`
- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Tasks/TaskDetailTest.php`

**Interfaces:**

- Consumes: owner-scoped `Task $task`, `TaskActivityFeedQuery::forTask()`, `TaskKeyQuery::displayKey()`, `TaskStatus::cases()`, and `TaskPriority::cases()`.
- Produces: `TaskDetailQuery::for(User|int $owner, Task $task): Task` and named route `tasks.show`.

- [ ] **Step 1: Write the failing detail tests**

```php
test('an owner can view a task detail page with its direct subtasks', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create([
        'number' => 2,
        'title' => 'Prepare checklist',
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-20',
    ]);

    $this->actingAs($owner)->get(route('tasks.show', $parent))
        ->assertOk()
        ->assertSee('PLAN-1')
        ->assertSee('Ship release')
        ->assertSee('Prepare checklist')
        ->assertSee('HIGH')
        ->assertSee('Sep 20, 2026');
});

test('a foreign task detail URL is not available', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->for($other)->create();

    $this->actingAs($owner)->get(route('tasks.show', $task))->assertNotFound();
});
```

- [ ] **Step 2: Run the focused test to confirm the detail route is missing**

Run `php artisan test tests/Feature/Tasks/TaskDetailTest.php`. Expected: PHP-unavailable failure in this environment or missing `tasks.show` failure when PHP is available.

- [ ] **Step 3: Implement the detail query and route**

Use this query contract:

```php
public function for(User|int $owner, Task $task): Task
{
    $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

    return Task::query()
        ->ownedBy($ownerId)
        ->whereKey($task->getKey())
        ->with([
            'project',
            'children' => fn (HasMany $children): HasMany => $children
                ->withCount('children')
                ->orderBy('position')
                ->orderBy('number'),
        ])
        ->firstOrFail();
}
```

Add `TaskController::show(Task $task, TaskDetailQuery $details): View` and pass the loaded task, `TaskStatus::cases()`, `TaskPriority::cases()`, and `TaskActivityFeedQuery::forTask($request->user(), $task)` to the view.

Register `GET /tasks/{task}` as `tasks.show` after the existing owner-scoped `task` binding.

- [ ] **Step 4: Build the shared detail view**

Render task key/title, project link, optional parent link, status, priority, due date, description, direct subtasks, and readable activity. Include the existing route-agnostic `components.tasks.metadata-form` with:

```blade
<x-tasks.metadata-form
    :task="$task"
    :priorities="$priorities"
    :update-action="route('tasks.update', $task)"
    :priority-action="route('tasks.priority', $task)"
    :due-date-action="route('tasks.due-date', $task)"
    :delete-action="route('tasks.destroy', $task)"
/>
```

Every task/subtask link must use `route('tasks.show', $task)`. Activity must render event type, field, old/new readable values, and timestamp; never output raw JSON.

- [ ] **Step 5: Run checks and commit**

Run `git diff --check` and `npm run build`. Commit:

```powershell
git add app/Domain/Tasks/Queries/TaskDetailQuery.php app/Http/Controllers/TaskController.php routes/web.php resources/views/pages/tasks/show.blade.php tests/Feature/Tasks/TaskDetailTest.php
git commit -m "feat: add editable task detail surface"
```

## Task 2: Expose existing task mutation actions through HTTP

**Files:**

- Modify: `app/Http/Controllers/TaskController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Tasks/TaskMetadataTest.php`

**Interfaces:**

- Consumes: `UpdateTask`, `ChangeTaskStatus`, `ChangeTaskPriority`, `ChangeTaskDueDate`, and `DeleteTask`.
- Produces: named routes `tasks.update`, `tasks.status`, `tasks.priority`, `tasks.due-date`, and `tasks.destroy`; every successful mutation redirects to `tasks.show`.

- [ ] **Step 1: Write failing HTTP mutation tests**

```php
test('task metadata HTTP actions save and return to task detail', function (): void {
    $owner = User::factory()->create();
    $task = Task::factory()->for($owner)->create();

    $this->actingAs($owner)->patch(route('tasks.update', $task), [
        'title' => 'Updated title',
        'description' => 'Updated description',
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    $this->actingAs($owner)->patch(route('tasks.priority', $task), [
        'priority' => TaskPriority::URGENT->value,
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    $this->actingAs($owner)->patch(route('tasks.due-date', $task), [
        'due_on' => '2026-10-01',
    ])->assertRedirect(route('tasks.show', $task, absolute: false));

    expect($task->fresh()->title)->toBe('Updated title')
        ->and($task->fresh()->priority)->toBe(TaskPriority::URGENT)
        ->and($task->fresh()->due_on?->toDateString())->toBe('2026-10-01');
});

test('task delete HTTP action soft deletes and returns to the project', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $task = Task::factory()->forProject($project)->create();

    $this->actingAs($owner)->delete(route('tasks.destroy', $task))
        ->assertRedirect(route('projects.show', $project, absolute: false));

    expect(Task::query()->find($task->id))->toBeNull();
});
```

- [ ] **Step 2: Run the focused tests to verify routes are missing**

Run `php artisan test tests/Feature/Tasks/TaskMetadataTest.php --filter='HTTP actions'`. Expected: missing route failures or the documented missing-PHP environment error.

- [ ] **Step 3: Add controller methods and routes**

Use these controller boundaries:

```php
public function update(UpdateTaskRequest $request, Task $task, UpdateTask $update): RedirectResponse
{
    $update->handle($request->user(), $task, $request->validated());

    return to_route('tasks.show', $task)->with('status', 'Task details updated.');
}
```

Follow the same pattern for priority, due date, and status. The delete method calls `DeleteTask` and redirects to `projects.show` using the task’s project id. Register the routes with the existing request classes and owner-scoped task binding.

- [ ] **Step 4: Verify authorization and redirects**

Run `php artisan test tests/Feature/Tasks/TaskMetadataTest.php tests/Feature/Tasks/ChangeTaskStatusTest.php`. Expected: pass when PHP is installed; otherwise report the missing executable. Commit:

```powershell
git add app/Http/Controllers/TaskController.php routes/web.php tests/Feature/Tasks/TaskMetadataTest.php
git commit -m "feat: expose task editing actions"
```

## Task 3: Render collapsible subtasks in the project overview

**Files:**

- Modify: `app/Domain/Projects/Queries/ProjectOverviewQuery.php`
- Modify: `resources/views/pages/projects/show.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/Projects/ProjectOverviewTest.php`

**Interfaces:**

- Consumes: project overview task collection with direct `children`, `children_count`, and task detail route.
- Produces: collapsed-by-default parent rows with accessible toggle buttons and independently editable child rows.

- [ ] **Step 1: Add failing project hierarchy assertions**

```php
test('the project overview exposes collapsed direct subtasks with independent metadata', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $parent = Task::factory()->forProject($project)->create(['number' => 1, 'title' => 'Ship release']);
    $child = Task::factory()->forProject($project)->withParent($parent)->create([
        'number' => 2,
        'title' => 'Prepare checklist',
        'status' => TaskStatus::IN_PROGRESS,
        'priority' => TaskPriority::HIGH,
        'due_on' => '2026-09-20',
    ]);

    $this->actingAs($owner)->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Show subtasks')
        ->assertSee('1 of 2 subtasks done')
        ->assertSee('Prepare checklist')
        ->assertSee(route('tasks.show', $child), false)
        ->assertSee('aria-expanded="false"', false)
        ->assertSee('aria-controls="subtasks-'.$parent->id.'"', false);
});
```

Use a parent with two children, one done and one in progress, for the final expected summary `1 of 2 subtasks done`.

- [ ] **Step 2: Run the focused test before changing the view**

Run `php artisan test tests/Feature/Projects/ProjectOverviewTest.php`. Expected: the accessibility and child-link assertions fail before the view changes, or PHP is unavailable.

- [ ] **Step 3: Load and summarize direct children**

In `ProjectOverviewQuery`, keep the existing top-level task query and eager-load `children` with `withCount('children')`. Add the non-cancelled direct-child count with this explicit aggregate when rendering the summary:

```php
->withCount([
    'children as eligible_children_count' => fn (Builder $children): Builder => $children
        ->where('status', '!=', TaskStatus::CANCELLED->value),
    'children as completed_children_count' => fn (Builder $children): Builder => $children
        ->where('status', TaskStatus::DONE->value),
])
```

Do not recursively eager-load grandchildren.

- [ ] **Step 4: Add the collapsed child region markup**

For each parent with children, render:

```blade
<button type="button"
        class="task-subtasks-toggle"
        aria-expanded="false"
        aria-controls="subtasks-{{ $task->id }}"
        data-subtasks-toggle="subtasks-{{ $task->id }}">
    <span data-subtasks-label>Show subtasks</span>
    <span class="sr-only">{{ $task->children_count }} subtasks for {{ $task->title }}</span>
</button>
<div id="subtasks-{{ $task->id }}" class="task-subtasks" hidden>
    @foreach ($task->children as $subtask)
        <a href="{{ route('tasks.show', $subtask) }}" class="task-subtask-row">
            <span>{{ $project->key }}-{{ $subtask->number }}</span>
            <span>{{ $subtask->title }}</span>
            <span>{{ str($subtask->status->value)->replace('_', ' ')->title() }}</span>
            <span>{{ str($subtask->priority->value)->replace('_', ' ')->title() }}</span>
            <span>{{ $subtask->due_on?->format('M j, Y') ?? 'No due date' }}</span>
            <span>Edit</span>
        </a>
    @endforeach
</div>
```

Use Alpine already loaded by the app shell for the toggle state, or a small `x-data` block in the component. The state must set both `hidden` and `aria-expanded`, and switch the visible label to `Hide subtasks`.

- [ ] **Step 5: Style parent/child hierarchy and commit**

Add an indented child surface, subtle left rule, readable metadata columns, visible focus ring, and a mobile stacked layout. Run `npm run build`, `git diff --check`, and the focused project overview tests. Commit:

```powershell
git add app/Domain/Projects/Queries/ProjectOverviewQuery.php resources/views/pages/projects/show.blade.php resources/css/app.css tests/Feature/Projects/ProjectOverviewTest.php
git commit -m "feat: add collapsible project subtasks"
```

## Task 4: Complete regression and accessibility verification

**Files:**

- Modify: `tests/Feature/Tasks/TaskDetailTest.php`
- Modify: `tests/Feature/Projects/ProjectOverviewTest.php`
- Modify: `tests/Feature/Tasks/TaskMetadataTest.php`

- [ ] **Step 1: Add regression coverage for independent parent/child metadata**

Assert that changing a child’s status, priority, or due date changes only the child; the parent values and project progress remain unchanged except for the top-level status effect.

- [ ] **Step 2: Add deletion and empty-state coverage**

Assert deleted tasks and children are absent, parents without children have no toggle, and a parent with only cancelled children displays explicit cancelled state with `No active subtasks`.

- [ ] **Step 3: Run the complete relevant suite**

```powershell
php artisan test tests/Feature/Projects tests/Feature/Tasks tests/Unit/Domain/Projects
npm run build
git diff --check
```

Expected: PHP/Pest tests pass when PHP is installed; otherwise report that PHP is unavailable. The frontend build and diff check must pass.

- [ ] **Step 4: Commit final test updates**

```powershell
git add tests/Feature/Tasks/TaskDetailTest.php tests/Feature/Projects/ProjectOverviewTest.php tests/Feature/Tasks/TaskMetadataTest.php
git commit -m "test: cover editable task hierarchy"
```

## Final verification and handoff

- [ ] Confirm `git status --short --branch` is clean on `main`.
- [ ] Run `npm run build` once more on the final commit stack.
- [ ] Run `git diff --check HEAD~4..HEAD`.
- [ ] If PHP is available, run the complete Laravel suite and record the exact result.
- [ ] If PHP is unavailable, explicitly state that Laravel tests were not executed.
- [ ] Do not push unless the user explicitly requests pushing the commits.
