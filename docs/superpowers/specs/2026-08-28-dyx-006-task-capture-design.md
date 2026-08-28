# DYX-006 Task Capture and Atomic Numbering Design

## Goal

Make task capture the next usable PlanOps workflow: an authenticated owner can
create a task inside an owned project, receive a stable project-local display
key such as `PLAN-1`, and see the creation recorded in the append-only activity
history.

## Scope

This slice implements the first task creation use case and its project-scoped
Blade screen:

- project-local number allocation that remains unique under concurrent writes;
- the `CreateTask` Action and `TaskKeyQuery` display-key formatter;
- owner-safe task creation policy and request validation;
- `GET /projects/{project}/tasks/create` and
  `POST /projects/{project}/tasks`;
- a compact create screen and reusable quick-create partial;
- failing-first tests for defaults, keys, activity, number stability, parent
  validation, and HTTP behavior.

Task editing, status transitions after creation, labels, soft deletion,
subtasks beyond parent validation, task lists, boards, and task detail remain
later dependency slices. The existing task schema, project counter, enums,
activity recorder, and owner-scoped project binding are reused.

## Product decisions

### Required and optional fields

The form keeps these fields visible:

- `Project` — the project context supplied by the route, presented as a
  labelled read-only value;
- `Title` — required, trimmed, and capped at 300 characters.

The accessible `More task details` disclosure contains:

- `Description` — optional text;
- `Status` — optional, default `NOT_STARTED`;
- `Priority` — optional, default `MEDIUM`;
- `Due date` — optional date-only value;
- `Parent task` — optional top-level task from the same project.

Label selection is intentionally deferred to DYX-007, where label
normalization and attachment Actions are implemented. `UpdateTaskRequest` is
also deferred to DYX-007; DYX-006 does not create an unused edit boundary.

### Stable identity

The database stores `project_id` and `number`; it does not store a duplicate
display key. `TaskKeyQuery::displayKey(Task $task): string` returns
`{project.key}-{task.number}` using the project relation and a stable uppercase
project key.

Numbers start at the project’s `next_task_number` value, which must be at least
`1`. A successful task creation increments the project counter. A soft-deleted
task still consumes its number; a failed transaction rolls back both the task
and the counter so no partially-created task can reserve an identity.

### Parent safety

If `parent_task_id` is provided, the parent must:

- exist and belong to the authenticated user;
- belong to the route project;
- have `parent_task_id = null` itself.

The task cannot be its own parent. A foreign project or owner is not exposed by
the parent query; a forged or nested parent fails validation and the Action
repeats the invariant for non-HTTP callers.

## Domain architecture

### Policy

`TaskPolicy::create(User $user, Project $project): bool` returns true only when
the user and project owner identifiers match. The request authorizes this
policy with the route-bound project. `CreateTask` authorizes the same policy
before beginning its transaction.

### CreateTask Action

The semantic interface is:

```php
CreateTask::handle(User $user, Project $project, array $attributes): Task
```

The Action normalizes string fields, resolves optional enum values, and applies
the documented defaults. Its transaction is:

1. authorize the owner/project pair;
2. start `DB::transaction()`;
3. reload the project with `lockForUpdate()`;
4. validate the counter and parent against the locked project and owner;
5. create the task with the allocated number, explicit user/project IDs,
   default status/priority, and `status_changed_at = now()`;
6. increment `next_task_number` exactly once;
7. record `TaskActivityType::TASK_CREATED` through
   `TaskActivityRecorder`;
8. commit and return the task with its project relation loaded.

The creation activity uses stable, non-sensitive context: its `new_value`
contains the derived task key, status, and priority; the title and description
are not copied into generic activity payloads. Any exception rolls back the
task, counter, and activity together.

### TaskKeyQuery

`TaskKeyQuery::displayKey(Task $task): string` is the single display-key
formatter for task creation responses and later task list/detail surfaces. It
uses a loaded project relation when available and loads only the project key
when it is not, so callers do not need to duplicate the `{key}-{number}` rule.

### Request and controller boundary

`StoreTaskRequest` owns HTTP validation and preparation:

- trim `title` and `description`;
- validate `title` as required string, max 300;
- validate `description` as nullable string;
- validate `status` against `TaskStatus` values when present;
- validate `priority` against `TaskPriority` values when present;
- validate `due_on` as a nullable date;
- validate `parent_task_id` as a nullable integer;
- authorize `TaskPolicy::create` for the route project.

`TaskController::create(Project $project): View` supplies the project,
top-level parent options, statuses, and priorities. `TaskController::store`
passes only `$request->validated()` to `CreateTask`, formats the new key with
`TaskKeyQuery`, and redirects to the same named create route with a status
message such as `PLAN-1 created.`.

The route binding already scopes `{project}` to the authenticated owner. The
Action remains authoritative for direct calls and cannot be bypassed by
calling the controller with a foreign model.

## Screen and visual system

The accepted Product Design option 3 remains the source of truth: a dark
PlanOps work console with a persistent 216px rail, open canvas, restrained
surface borders, a single lime primary action, teal links, Figtree typography,
and Phosphor regular icons. The form extends that system rather than adding a
new card family or a marketing wrapper.

### Layout

- The authenticated rail remains visible on desktop and collapses behind the
  existing labelled mobile menu at smaller widths.
- The main content uses the existing Projects console container and spacing
  tokens.
- The form is one purposeful bordered surface with a clear title, project
  context, required title input, optional details disclosure, and action row.
- `Create task` is the primary action; `Cancel` returns to the project edit
  route.
- The success message is a semantic status region and preserves the derived
  key in text.

### Interaction and accessibility

- Every input/select/textarea has an explicit `<label>` association.
- The disclosure uses native `<details>`/`<summary>` semantics or equivalent
  keyboard-operable disclosure behavior.
- Validation errors are rendered adjacent to their fields and are not conveyed
  by color alone.
- Focus-visible rings use the existing lime token in both themes.
- Decorative Phosphor icons carry `aria-hidden="true"`; action meaning remains
  in visible text.
- The project context is readable on mobile without requiring a hover state.
- Reduced-motion preferences continue to disable non-essential transitions.

## Failure behavior

- Guests are redirected by the existing `auth` middleware.
- A foreign project resolves as `404` through the owner-scoped route binding.
- A foreign, cross-project, nested, or self-parent fails at the request/action
  boundary without creating a task.
- Invalid fields return `422`, preserve safe `old()` values, and show specific
  field errors.
- Counter, task, and activity writes share one transaction; a database or
  activity failure leaves no task and does not advance the counter.
- The existing unique `(project_id, number)` index remains the final database
  guard against accidental duplicate allocation.

## Test contract

### Unit: `tests/Unit/Domain/Tasks/TaskKeyTest.php`

Cover:

- `PLAN-1` is derived from a task numbered `1` in project `PLAN`;
- the formatter uses the project relation and does not require a stored key;
- a missing project identity is rejected rather than producing a malformed
  display identifier.

### Feature: `tests/Feature/Tasks/CreateTaskTest.php`

Cover:

- an owner can create a task with defaults `NOT_STARTED` and `MEDIUM`;
- the first task receives number `1` and display key `PLAN-1`;
- optional description/status/priority/due date values persist after creation;
- one `TASK_CREATED` activity is attached with owner/project/task context and
  stable key/status/priority metadata without title/description text;
- a second task receives the next number;
- a soft-deleted prior task does not cause number reuse;
- foreign project, foreign parent, cross-project parent, nested parent, and
  self-parent attempts are rejected;
- the create route renders labelled controls and redirects with the derived
  key message after a valid POST.

### Feature: `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`

Cover the transaction contract with two creation attempts for the same project
and assert that successful tasks receive distinct sequential numbers, the
project counter advances by the number of successful tasks, and the unique
database key is never duplicated. The test uses the project’s real database
connection and transaction lock rather than mocking the counter.

## File inventory

### Create

- `app/Domain/Tasks/Actions/CreateTask.php`
- `app/Domain/Tasks/Queries/TaskKeyQuery.php`
- `app/Policies/TaskPolicy.php`
- `app/Http/Controllers/TaskController.php`
- `app/Http/Requests/StoreTaskRequest.php`
- `resources/views/pages/tasks/create.blade.php`
- `resources/views/components/tasks/quick-create.blade.php`
- `tests/Unit/Domain/Tasks/TaskKeyTest.php`
- `tests/Feature/Tasks/CreateTaskTest.php`
- `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`

### Modify

- `app/Domain/Tasks/Models/Task.php` only when needed for the display-key
  integration or creation defaults;
- `routes/web.php` for the two project-scoped task creation routes;
- `docs/superpowers/plans/2026-08-20-planops-implementation.md` to mark only
  the DYX-006 checklist items addressed by this slice.

## Non-goals

- no labels or label attachment;
- no general task editing request or update endpoint;
- no automatic task/project status changes;
- no task movement between projects;
- no task list, board, activity timeline, dashboard, analytics, or search
  implementation;
- no organizations, roles, queues, schedulers, WebSockets, or new tables.

## Acceptance criteria

1. A valid owner request creates one task with a unique project-local number,
   correct defaults, and a same-transaction `TASK_CREATED` activity.
2. The derived display key is stable, readable, and not stored redundantly.
3. A failed or unauthorized request creates no task and does not leak another
   user’s project or parent options.
4. Concurrent creation cannot produce duplicate `(project_id, number)` values.
5. The screen is short, labelled, keyboard-operable, responsive, and visually
   continuous with the accepted option 3 console.
6. Tests are written before production code; runtime test status is reported
   honestly if the PHP 8.3 executable remains unavailable.
