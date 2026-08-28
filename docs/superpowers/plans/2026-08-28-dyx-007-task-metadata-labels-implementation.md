# DYX-007 Task Metadata, Due State, Labels, and Soft Deletion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task with review checkpoints. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add owner-safe task metadata mutations, date-only overdue computation, normalized labels, and recoverable task deletion on top of DYX-006.

**Architecture:** Keep each meaningful mutation in a small domain Action that authorizes before a database transaction and records one append-only activity event for each actual change. Keep overdue state derived from the caller’s user-local date, keep label ownership explicit in `LabelPolicy` and label Actions, and expose the UI as reusable Blade form components whose action URLs are supplied by the later task-detail adapter.

**Tech Stack:** Laravel 13 / PHP 8.3, Eloquent, Pest, Blade, Vite, existing PlanOps CSS tokens, Phosphor icons, and the existing `TaskActivityRecorder`.

**Spec:** `planops-complete-spec.md` sections 25–30, 31–33, 64–67, 101–109; implementation boundary `docs/superpowers/plans/2026-08-20-planops-implementation.md` Task 7 (DYX-007).

## Global Constraints

- Use Laravel 13 / PHP 8.3 and the existing project structure; do not introduce a new framework or persistence table.
- Scope every task, label, relation, query, Action, and policy by `user_id`; cross-user resources must not be attachable or mutable.
- Keep task priority values exactly `LOW`, `MEDIUM`, `HIGH`, and `URGENT`; do not use priority as workflow state.
- Keep `due_on` as a date-only value; overdue means `user_local_date > due_on` and status is neither `DONE` nor `CANCELLED`.
- Keep labels unique per user after normalized comparison; deleting a label detaches it without deleting tasks.
- Use soft deletion for tasks; ordinary Task queries exclude deleted rows, while restore/history code uses `withTrashed()` only at the explicit restoration boundary.
- Record only meaningful mutations through `TaskActivityRecorder`; never put full title or description text in generic update `old_value`/`new_value` payloads.
- Do not add task movement, custom statuses, automatic status changes, autosave activity, or label controls outside this DYX-007 boundary.
- Essential controls must remain keyboard-operable, visibly focused, text-labelled, responsive at 390px, and compatible with reduced motion; color cannot be the only meaning.

---

### Task 1: Write the failing DYX-007 contract

**Files:**
- Create: `tests/Unit/Domain/Tasks/OverdueTaskTest.php`
- Create: `tests/Unit/Domain/Labels/LabelNormalizationTest.php`
- Create: `tests/Feature/Tasks/TaskMetadataTest.php`
- Create: `tests/Feature/Labels/LabelManagementTest.php`

**Interfaces:**
- Consumes: DYX-003 models/enums, DYX-004 `TaskActivityRecorder`, and DYX-006 `Task`/`TaskKeyQuery` behavior.
- Produces: executable expectations for `Task::isOverdueOn(CarbonImmutable $userLocalDate): bool`, metadata Actions, label Actions, and deletion/restore Actions.

- [ ] **Step 1: Write the overdue and normalization tests first.**

  `OverdueTaskTest.php` must create tasks with date-only `due_on` values and assert:

  ```php
  test('a task is overdue only after its user-local due date', function (): void {
      $task = Task::factory()->create([
          'due_on' => '2026-08-27',
          'status' => TaskStatus::IN_PROGRESS,
      ]);

      expect($task->isOverdueOn(CarbonImmutable::parse('2026-08-27')))->toBeFalse()
          ->and($task->isOverdueOn(CarbonImmutable::parse('2026-08-28')))->toBeTrue();
  });
  ```

  Also cover a future date, `null` due date, `DONE`, and `CANCELLED`; none of those may be overdue. `LabelNormalizationTest.php` must assert that display names are trimmed/squished, normalized names are lowercase, and `Frontend`, ` frontend `, and `FRONTEND` collide for one owner but not for a second owner.

- [ ] **Step 2: Write metadata Action tests.**

  In `TaskMetadataTest.php`, cover:

  - `UpdateTask::handle(User $user, Task $task, array $attributes): Task` trims title/description, persists nullable description, records `TASK_UPDATED` only for changed fields, and never stores the old or new full title/description text in activity JSON.
  - `ChangeTaskPriority::handle(User $user, Task $task, TaskPriority|string $priority): Task` accepts all four enum values, persists the enum, records `PRIORITY_CHANGED`, and creates no event for an identical value.
  - `ChangeTaskDueDate::handle(User $user, Task $task, CarbonImmutable|string|null $dueOn): Task` persists a date-only value, records `DUE_DATE_CHANGED`, handles clearing the date, and creates no event for an identical value.
  - every metadata Action rejects a task owned by another user without changing the row or activity count.

- [ ] **Step 3: Write label and soft-delete tests.**

  In `LabelManagementTest.php`, cover:

  - `CreateLabel::handle(User $user, array $attributes): Label` normalizes names and rejects a duplicate normalized name for the same owner while allowing the same normalized name for another owner;
  - `AttachLabelToTask::handle(User $user, Task $task, Label $label): Task` and `DetachLabelFromTask::handle(...)` enforce same-user ownership, are idempotent for an already-attached/missing pivot, and record `LABEL_ADDED`/`LABEL_REMOVED` only when a pivot changes;
  - `DeleteLabel::handle(User $user, Label $label): void` detaches the label from every owned task, leaves tasks intact, and records one removal event per detached task;
  - `DeleteTask::handle(User $user, Task $task): Task` soft-deletes the task, keeps its number and activity history, emits `TASK_DELETED`, and removes it from a normal `Task::query()` result;
  - `RestoreTask::handle(User $user, Task $task): Task` restores only an explicitly trashed task, emits `TASK_RESTORED`, and preserves the task identity and labels;
  - deletion/restoration by another user is rejected without mutation.

- [ ] **Step 4: Run the focused contract command to establish the red/environment state.**

  Run:

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/OverdueTaskTest.php tests/Unit/Domain/Labels/LabelNormalizationTest.php tests/Feature/Tasks/TaskMetadataTest.php tests/Feature/Labels/LabelManagementTest.php
  ```

  Expected result on this host: PowerShell reports that `php` is not recognized before Laravel boots. Preserve that exact limitation in the task report; do not replace it with a false pass.

- [ ] **Step 5: Commit the red contract.**

  ```powershell
  git add -- tests/Unit/Domain/Tasks/OverdueTaskTest.php tests/Unit/Domain/Labels/LabelNormalizationTest.php tests/Feature/Tasks/TaskMetadataTest.php tests/Feature/Labels/LabelManagementTest.php
  git commit -m "test: define task metadata label and deletion contract"
  ```

---

### Task 2: Implement derived due state, label normalization, and policies

**Files:**
- Create: `app/Domain/Tasks/Rules/OverdueTask.php`
- Create: `app/Domain/Labels/Rules/NormalizedLabelName.php`
- Create: `app/Policies/LabelPolicy.php`
- Modify: `app/Domain/Tasks/Models/Task.php`
- Modify: `app/Policies/TaskPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Domain/Tasks/OverdueTaskTest.php`, `tests/Unit/Domain/Labels/LabelNormalizationTest.php`

**Interfaces:**
- Consumes: `TaskStatus`, `Task`, `Label`, and authenticated `User`.
- Produces: `OverdueTask::passes(Task $task, CarbonImmutable $userLocalDate): bool`, `Task::isOverdueOn(CarbonImmutable $userLocalDate): bool`, `NormalizedLabelName::displayName(string $name): string`, `NormalizedLabelName::normalize(string $name): string`, and registered task/label policy methods.

- [ ] **Step 1: Implement the smallest overdue rule and model delegate.**

  `OverdueTask::passes()` must return `false` when `due_on` is `null`, when the local date is equal to or earlier than `due_on`, or when the status is `DONE`/`CANCELLED`. Compare `toDateString()` values so no time-of-day or fake `23:59` timestamp is introduced. `Task::isOverdueOn()` delegates to one `OverdueTask` instance and remains a pure derived read.

- [ ] **Step 2: Implement normalized label names.**

  Use Laravel’s string helpers to collapse repeated whitespace, trim the display name, and lowercase the normalized value:

  ```php
  public function displayName(string $name): string
  {
      return (string) str($name)->squish();
  }

  public function normalize(string $name): string
  {
      return (string) str($this->displayName($name))->lower();
  }
  ```

  An empty normalized value is invalid and must be rejected by `CreateLabel` in Task 4; this rule must not silently turn whitespace into a valid label.

- [ ] **Step 3: Extend ownership policy boundaries.**

  Add `update`, `changePriority`, `changeDueDate`, `delete`, and `restore` to `TaskPolicy`; each returns a string-safe `user_id` equality check. Add `viewAny`, `create`, `delete`, `attach`, and `detach` to `LabelPolicy`. `attach` and `detach` accept `(User $user, Label $label, Task $task)` and require both resources to have the same owner as the user. Register `Label::class` with `LabelPolicy::class` beside the existing project and task policies.

- [ ] **Step 4: Run the unit contract.**

  ```powershell
  php artisan test tests/Unit/Domain/Tasks/OverdueTaskTest.php tests/Unit/Domain/Labels/LabelNormalizationTest.php
  ```

  Expected on a PHP-enabled host: PASS. On this host, record the missing-`php` executable result.

- [ ] **Step 5: Commit the rule and policy boundary.**

  ```powershell
  git add -- app/Domain/Tasks/Rules/OverdueTask.php app/Domain/Labels/Rules/NormalizedLabelName.php app/Domain/Tasks/Models/Task.php app/Policies/TaskPolicy.php app/Policies/LabelPolicy.php app/Providers/AppServiceProvider.php
  git commit -m "feat: add task due state and label policies"
  ```

---

### Task 3: Implement task metadata Actions and request contracts

**Files:**
- Create: `app/Domain/Tasks/Actions/UpdateTask.php`
- Create: `app/Domain/Tasks/Actions/ChangeTaskPriority.php`
- Create: `app/Domain/Tasks/Actions/ChangeTaskDueDate.php`
- Create: `app/Http/Requests/UpdateTaskRequest.php`
- Create: `app/Http/Requests/ChangeTaskPriorityRequest.php`
- Create: `app/Http/Requests/ChangeTaskDueDateRequest.php`
- Test: `tests/Feature/Tasks/TaskMetadataTest.php`

**Interfaces:**
- Consumes: task policy methods, `TaskActivityRecorder`, `TaskPriority`, `Task`, and the date-only task cast.
- Produces: `UpdateTask::handle(User $user, Task $task, array $attributes): Task`, `ChangeTaskPriority::handle(User $user, Task $task, TaskPriority|string $priority): Task`, and `ChangeTaskDueDate::handle(User $user, Task $task, CarbonImmutable|string|null $dueOn): Task`.

- [ ] **Step 1: Implement `UpdateTask` with a narrow field boundary.**

  Accept only `title` and `description`. Trim both strings, convert an empty description to `null`, require a non-empty title of at most 300 characters, authorize with `update` before opening the transaction, and update only fields that actually changed. Inside the transaction, record one `TASK_UPDATED` event per changed field using `field = 'title'` or `field = 'description'`; pass old/new values to the existing recorder so its redaction rule stores no full text. Return the refreshed owner-scoped task.

- [ ] **Step 2: Implement priority mutation.**

  Normalize a string to `TaskPriority::from()` and accept an enum directly. Reject invalid values with `ValidationException`, authorize `changePriority`, no-op identical values, and otherwise update inside `DB::transaction()`. Record `PRIORITY_CHANGED` with `field = 'priority'`, old/new enum values, and no title/description metadata.

- [ ] **Step 3: Implement date-only due-date mutation.**

  Accept `CarbonImmutable`, a `Y-m-d` string, or `null`. Normalize strings with `CarbonImmutable::createFromFormat('!Y-m-d', $value)` and reject malformed dates with `ValidationException`; compare and persist only the date component. Authorize `changeDueDate`, no-op identical values, and record `DUE_DATE_CHANGED` with `field = 'due_on'` and old/new `Y-m-d` strings or `null` inside the same transaction.

- [ ] **Step 4: Implement HTTP validation contracts without widening scope.**

  `UpdateTaskRequest` validates only `title` and nullable `description`, trims them in `prepareForValidation()`, and authorizes the route-bound `Task` with `update`. `ChangeTaskPriorityRequest` validates `priority` against `TaskPriority::cases()` and authorizes `changePriority`. `ChangeTaskDueDateRequest` validates nullable `due_on` as a date and authorizes `changeDueDate`. None of these requests may contain a mass-assignment wildcard or an unscoped task existence rule.

- [ ] **Step 5: Run focused metadata coverage.**

  ```powershell
  php artisan test tests/Feature/Tasks/TaskMetadataTest.php
  ```

  Expected on a PHP-enabled host: PASS, including no-op activity counts, redaction, enum/date persistence, and cross-user rejection. Record the missing-`php` result here on this host.

- [ ] **Step 6: Commit the metadata boundary.**

  ```powershell
  git add -- app/Domain/Tasks/Actions/UpdateTask.php app/Domain/Tasks/Actions/ChangeTaskPriority.php app/Domain/Tasks/Actions/ChangeTaskDueDate.php app/Http/Requests/UpdateTaskRequest.php app/Http/Requests/ChangeTaskPriorityRequest.php app/Http/Requests/ChangeTaskDueDateRequest.php
  git commit -m "feat: add task metadata actions"
  ```

---

### Task 4: Implement normalized labels and label activity

**Files:**
- Create: `app/Domain/Labels/Actions/CreateLabel.php`
- Create: `app/Domain/Labels/Actions/AttachLabelToTask.php`
- Create: `app/Domain/Labels/Actions/DetachLabelFromTask.php`
- Create: `app/Domain/Labels/Actions/DeleteLabel.php`
- Create: `app/Http/Requests/StoreLabelRequest.php`
- Modify: `app/Domain/Labels/Models/Label.php`
- Test: `tests/Feature/Labels/LabelManagementTest.php`

**Interfaces:**
- Consumes: `NormalizedLabelName`, `LabelPolicy`, `TaskActivityRecorder`, `Label`, and `Task`.
- Produces: `CreateLabel::handle(User $user, array $attributes): Label`, `AttachLabelToTask::handle(User $user, Task $task, Label $label): Task`, `DetachLabelFromTask::handle(User $user, Task $task, Label $label): Task`, and `DeleteLabel::handle(User $user, Label $label): void`.

- [ ] **Step 1: Implement label creation and request validation.**

  `CreateLabel` authorizes `create`, uses `displayName()` for the stored `name`, uses `normalize()` for `normalized_name`, rejects an empty name, validates `max:80`, accepts an optional `color` up to 32 characters, and lets the database unique index enforce the final per-user race-safe uniqueness. `StoreLabelRequest` mirrors these rules and authorizes the authenticated user to create a label.

- [ ] **Step 2: Implement attach/detach with explicit owner and pivot checks.**

  Authorize `attach`/`detach` before each transaction, require the task and label `user_id` to match the authenticated user, and reject a cross-user or mismatched resource even if the caller manually constructed the models. `AttachLabelToTask` checks the existing pivot before attaching; if absent, attach once and record `LABEL_ADDED` with `field = 'label_id'`, `old_value = null`, `new_value = label id`, and metadata containing only the label id/name. `DetachLabelFromTask` records the inverse only when a pivot existed.

- [ ] **Step 3: Implement label deletion as an atomic detach-and-delete.**

  `DeleteLabel` authorizes `delete`, loads only tasks owned by the same user that currently carry the label, records one `LABEL_REMOVED` event for each detached task, detaches the pivot rows, and deletes the label in one transaction. It must not delete or soft-delete any task. A second delete attempt is a no-op or a policy failure without new task mutations; choose the existing model/policy convention and cover it in the test.

- [ ] **Step 4: Add label relationship scope helpers.**

  Keep `Label::ownedBy()` as the canonical owner scope and add only narrow relationship/query helpers needed by the Actions; do not add a global label binding or a cross-user convenience relation. The `Task::labels()` and `Label::tasks()` pivot relations remain many-to-many with the existing `task_label` unique key.

- [ ] **Step 5: Run label coverage.**

  ```powershell
  php artisan test tests/Feature/Labels/LabelManagementTest.php
  ```

  Expected on a PHP-enabled host: PASS with normalized uniqueness, owner-safe attachment, idempotent pivot operations, per-task removal events, and intact tasks after label deletion. Record the missing-`php` result on this host.

- [ ] **Step 6: Commit the label boundary.**

  ```powershell
  git add -- app/Domain/Labels app/Http/Requests/StoreLabelRequest.php tests/Feature/Labels/LabelManagementTest.php
  git commit -m "feat: add normalized task labels"
  ```

---

### Task 5: Implement recoverable task deletion and restoration

**Files:**
- Create: `app/Domain/Tasks/Actions/DeleteTask.php`
- Create: `app/Domain/Tasks/Actions/RestoreTask.php`
- Test: `tests/Feature/Tasks/TaskMetadataTest.php`

**Interfaces:**
- Consumes: `TaskPolicy`, `TaskActivityRecorder`, `Task` with `SoftDeletes`, and label/activity relationships.
- Produces: `DeleteTask::handle(User $user, Task $task): Task` and `RestoreTask::handle(User $user, Task $task): Task`.

- [ ] **Step 1: Implement `DeleteTask` atomically.**

  Authorize `delete` before the transaction. If the task is active, call `delete()` and record `TASK_DELETED` after the soft-delete mutation in the same transaction; do not decrement the project counter, detach labels, alter the task number, or change activity history. If the task is already trashed, do not create a duplicate deletion event.

- [ ] **Step 2: Implement `RestoreTask` at the explicit restoration boundary.**

  Authorize `restore`, require the supplied task to be trashed, call `restore()`, and record `TASK_RESTORED` in one transaction. The Action must not issue a broad `withTrashed()` query; callers that need to find a deleted row must opt into `Task::withTrashed()` before calling it. Preserve the task’s project, number, labels, due date, priority, and prior activity.

- [ ] **Step 3: Add the UI confirmation contract to the reusable metadata form’s optional delete slot.**

  The delete control must be a text-labelled `DELETE` form supplied with an action URL, include CSRF/method spoofing, and use a native confirmation step such as `onsubmit="return window.confirm('Delete this task?')"`; the server Action remains the authorization and transaction boundary. No destructive action may be triggered by page load, hover, or a client-side state change.

- [ ] **Step 4: Run deletion coverage.**

  ```powershell
  php artisan test tests/Feature/Tasks/TaskMetadataTest.php --filter='delete|restore|soft'
  ```

  Expected on a PHP-enabled host: PASS with active-query exclusion, identity preservation, append-only events, label preservation, and cross-user rejection. Record the missing-`php` result on this host.

- [ ] **Step 5: Commit the deletion boundary.**

  ```powershell
  git add -- app/Domain/Tasks/Actions/DeleteTask.php app/Domain/Tasks/Actions/RestoreTask.php tests/Feature/Tasks/TaskMetadataTest.php
  git commit -m "feat: add recoverable task deletion"
  ```

---

### Task 6: Build reusable metadata and label UI components

**Files:**
- Create: `resources/views/components/tasks/metadata-form.blade.php`
- Create: `resources/views/components/labels/label-picker.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/Tasks/TaskMetadataTest.php` (Blade contract assertions)

**Interfaces:**
- Consumes: a task, `TaskPriority::cases()`, owned label options, old input/errors, and action URLs supplied by a future task-detail controller.
- Produces: keyboard-operable metadata and label form fragments with explicit props for update, priority, due-date, delete, attach, detach, and create-label actions; no route is hard-coded into a reusable component.

- [ ] **Step 1: Define the metadata component props and fields.**

  `metadata-form.blade.php` must accept `$task`, `$priorities`, `$updateAction`, `$priorityAction`, `$dueDateAction`, and an optional `$deleteAction`. Render associated labels and controls for Title, Description, Priority, and Due date; use `old()` values, adjacent `x-input-error` messages, `@csrf`, and method spoofing. Render a delete form only when `$deleteAction` is non-null and include a visible confirmation label.

- [ ] **Step 2: Define the label picker props and controls.**

  `label-picker.blade.php` must accept `$labels`, `$selectedLabelIds`, `$attachAction`, `$detachAction`, and an optional `$createAction`. Render a labelled single-label select/add control, a text list of attached labels, and one text-labelled remove form per selected label. Preserve a visible fallback such as `No labels attached.`; label color may be decorative but never the only label meaning.

- [ ] **Step 3: Apply the Product Design option-3 visual contract.**

  Use existing `--planops-*` variables, Figtree, restrained borders, 6–10px radii, and Phosphor icons only as `aria-hidden="true"` decoration beside visible text. Add component classes with at least `2.75rem` control targets, a lime `:focus-visible` ring, readable error/status styles, stacked controls at `max-width: 480px`, and no gradients, glass, glow, or hover-only actions. Preserve the existing `prefers-reduced-motion: reduce` rule.

- [ ] **Step 4: Add static Blade contract assertions.**

  Assert the component source contains the required field labels, CSRF/method directives, error components, `aria-hidden="true"` icon usage, visible delete confirmation, and no label input named `display_key`. Keep assertions focused on the component contract; do not require a browser runtime that is unavailable on this host.

- [ ] **Step 5: Run UI checks.**

  ```powershell
  php artisan test tests/Feature/Tasks/TaskMetadataTest.php --filter='metadata|label|form'
  npm run build
  git diff --check
  ```

  Expected on a PHP-enabled host: Blade contract tests pass. Expected on this host: PHP command reports missing `php`; Vite build and whitespace check pass.

- [ ] **Step 6: Commit the reusable UI boundary.**

  ```powershell
  git add -- resources/views/components/tasks/metadata-form.blade.php resources/views/components/labels/label-picker.blade.php resources/css/app.css tests/Feature/Tasks/TaskMetadataTest.php
  git commit -m "feat: add task metadata and label controls"
  ```

---

### Task 7: Verify, review, update the master plan, and synchronize DYX-007

**Files:**
- Modify: `docs/superpowers/plans/2026-08-20-planops-implementation.md` in the DYX-007 section only
- Test: all DYX-007 unit/feature tests, `npm run build`, `git diff --check`, and static UI inspection

**Interfaces:**
- Consumes: all DYX-007 Actions, rules, policies, requests, tests, and reusable components from Tasks 1–6.
- Produces: reviewable evidence, master-plan bookkeeping with only executable checks marked complete, and a multi-commit `main` history synchronized to `origin/main`.

- [ ] **Step 1: Run the complete available verification.**

  ```powershell
  php artisan test tests/Unit/Domain/Tasks tests/Unit/Domain/Labels tests/Feature/Tasks tests/Feature/Labels
  php artisan test --parallel
  npm run build
  git diff --check HEAD~6..HEAD
  ```

  Record exact outcomes. PHP-dependent commands may remain blocked by the missing executable; never report them as passing. The Vite build and diff check must pass before synchronization.

- [ ] **Step 2: Perform source-level UI checks at the acceptance dimensions.**

  Inspect the exact Blade/CSS source for one associated label per control, visible focus, text status/error meaning, 44px controls, responsive stacking at 390px, native confirmation for delete, and reduced-motion preservation. If Browser/Playwright is available, render the task-detail host screen at `1440×1024` and `390×844`; otherwise create only an ignored scratch note that names the missing runtime and does not stand in for a screenshot.

- [ ] **Step 3: Reconcile only DYX-007 markers in the master plan.**

  Mark DYX-007 Steps 1, 3, 4, and 6 `[x]`. Mark Steps 2 and 5 `[x]` only if their PHP tests actually execute; otherwise leave them unchecked and add a verification note directly below DYX-007. Do not change DYX-008 or later markers, and do not claim task-detail route integration that belongs to later screen tasks.

- [ ] **Step 4: Commit the verification boundary.**

  ```powershell
  git add -- docs/superpowers/plans/2026-08-20-planops-implementation.md
  git commit -m "docs: record DYX-007 verification boundary"
  ```

- [ ] **Step 5: Push and verify remote synchronization.**

  ```powershell
  git push origin main
  git status --short --branch
  git rev-list --left-right --count origin/main...main
  ```

  Expected result: push succeeds to `https://github.com/Hamdaoui-Ali/PlanOps`, the worktree is clean, and divergence is `0 0`.

## Plan self-review

- Spec coverage: metadata title/description edits, all four priorities, date-only due dates, local overdue computation, normalized per-user labels, attach/detach/delete behavior, activity events, soft deletion/restoration, active-query exclusion, confirmation, responsive/accessibility styling, and PHP/browser verification limits each have an explicit task.
- Scope: no task movement, custom workflow, automatic status mutation, new persistent table, or DYX-008+ feature is added.
- Interface consistency: Action signatures are defined once in their task’s interface block and reused by tests, requests, and components.
- Empty/invalid values are explicit: empty descriptions become `null`, empty label names fail validation, `null` due dates clear the value, and no-op mutations create no activity.
- No placeholders or deferred implementation language is used in the executable steps; the only environment-dependent result is the documented PHP/browser verification boundary.
