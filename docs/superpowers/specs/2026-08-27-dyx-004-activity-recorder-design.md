# DYX-004 Activity Recorder Design

Date: 2026-08-27

Status: ready for review

## Goal

Add the append-only activity foundation that later task Actions, task
timelines, project feeds, dashboards, and analytics will consume:

- one explicit `TaskActivityRecorder` write boundary;
- one normalized payload contract for meaningful task changes;
- application-level protection against updating or deleting activity rows;
- one user-scoped query boundary for chronological activity reads.

This slice is deliberately limited to the activity domain. It does not add
task or project mutation Actions, HTTP routes, activity pages, dashboard
queries, analytics calculations, or a new visual surface.

## Context and constraints

The repository is a Laravel 13 modular monolith using PHP 8.3, PostgreSQL in
production, SQLite for the existing in-memory tests, Eloquent models, and
Pest/PHPUnit. DYX-003 already provides the `TaskActivity` table, model,
`TaskActivityType` backed enum, task/project/user relationships, and
ownership scopes.

Activity rows are historical facts. They must keep their task and project
context after a task is soft-deleted, remain scoped to the owning user, and
never be used to infer computer activity, hours worked, or productivity.

The activity table is already PostgreSQL-first (`jsonb`) and test-compatible
with the repository's SQLite grammar. This design does not change the schema.

## Chosen approach

Use an explicit service rather than Eloquent observers or database triggers.
Every future domain Action will call `TaskActivityRecorder::record()` after
it has decided that a meaningful user-declared mutation occurred. The
recorder normalizes the payload but never decides task state or timestamps.

Protect the model's update and delete lifecycle events so ordinary Eloquent
application code cannot mutate existing activity rows. Query-builder bulk
updates are not used by the application; database-level trigger enforcement
is intentionally deferred because it would couple this portable Laravel
baseline to PostgreSQL-specific trigger code.

Provide `TaskActivityFeedQuery` as the read boundary. It applies the owner
scope before optional filters, eagerly loads the historical task context with
soft-deleted tasks included, and gives callers stable ordering and pagination.
Timezone conversion remains the responsibility of the period resolver that
will be introduced in DYX-015; this query accepts already-resolved UTC
bounds.

### Alternatives rejected

**Eloquent observers** would hide the source of each event and could record
internal saves or noisy changes. Explicit Actions are easier to audit and
test against the product rule that history records meaningful mutations only.

**Database triggers** could enforce append-only storage more strongly, but
would add database-specific behaviour before the application contract is
stable and would be awkward to exercise in the existing SQLite test suite.

## Recorder contract

The public semantic signature is:

```php
TaskActivityRecorder::record(
    Task $task,
    TaskActivityType $type,
    ?string $field,
    mixed $oldValue,
    mixed $newValue,
    array $metadata = [],
): TaskActivity
```

The recorder writes the following context directly from the supplied task:

```text
user_id    = task.user_id
project_id = task.project_id
task_id    = task.id
event_type = TaskActivityType->value
field      = supplied field, or null
```

The task must already exist and have a user, project, and primary key. The
recorder does not accept caller-supplied ownership ids, so a future Action
cannot accidentally attach an event to another user's context.

## Normalized payload contract

`old_value`, `new_value`, and `metadata` are nullable JSON objects or JSON
scalars after normalization. The recorder applies these rules:

1. Backed enums become their backed string value. For example,
   `TaskStatus::IN_PROGRESS` is persisted as `IN_PROGRESS`, never as a PHP
   serialization or enum class name.
2. `DateTimeInterface` values are converted to UTC ISO-8601 strings. Date-only
   values such as `due_on` are passed as `YYYY-MM-DD` strings and remain date
   only.
3. Arrays are normalized recursively without changing their keys. Booleans,
   numbers, strings, and null remain JSON-safe values.
4. Field-based scalar values are wrapped in an object using the field name so
   persisted payloads have one readable shape. For example, a status change
   becomes `{"status":"DONE"}` in `new_value`.
5. A generic `TASK_UPDATED` event for `title` or `description` retains the
   field name but stores `null` for both old and new values. Full task text is
   never duplicated into the activity history. Other safe generic fields may
   use the normalized old/new objects.
6. Metadata is normalized with the same rules. `is_reopen` is preserved as a
   boolean when supplied; the recorder does not infer it from the statuses.
   Known text fields such as `title` and `description` are removed from
   metadata so callers cannot reintroduce the redacted task body there.

The event type remains the authoritative discriminator. The following event
types are supported by the contract:

| Event type | Expected `field` | Payload intent |
| --- | --- | --- |
| `TASK_CREATED` | `null` | Safe initial task attributes in `new_value`, if provided. |
| `TASK_UPDATED` | Changed field | Safe normalized values, or redacted nulls for title/description. |
| `STATUS_CHANGED` | `status` | Old/new status objects with stable enum strings. |
| `PRIORITY_CHANGED` | `priority` | Old/new priority objects with stable enum strings. |
| `DUE_DATE_CHANGED` | `due_on` | Old/new date objects; a cleared date is represented by `due_on: null`. |
| `LABEL_ADDED` | `label` | Safe label identity/details supplied by the label Action. |
| `LABEL_REMOVED` | `label` | Safe label identity/details supplied by the label Action. |
| `SUBTASK_CREATED` | `null` | Safe child task identity supplied by the subtask Action. |
| `TASK_MOVED_PROJECT` | `project_id` | Reserved for a future migration transaction; no v1 UI invokes it. |
| `TASK_DELETED` | `null` | No task body copy; optional safe metadata only. |
| `TASK_RESTORED` | `null` | No task body copy; optional safe metadata only. |

The recorder accepts the complete enum set so future Actions share one API,
but it does not make `TASK_MOVED_PROJECT` reachable from a v1 route or UI.

## Append-only model boundary

`TaskActivity` keeps its existing relationships, casts, fillable creation
attributes, and `created_at`-only timestamp contract. DYX-004 adds:

- an immutable datetime cast for `created_at`;
- model lifecycle guards that throw a clear `LogicException` on `update()` or
  `delete()` of an existing activity model;
- no update, delete, or restore Action and no UI control that exposes one.

The guard protects normal model operations. The application will not use
bulk query-builder mutations against `task_activities`; any future stronger
database enforcement is a separate production-hardening decision.

## Feed query contract

`TaskActivityFeedQuery` exposes two read methods:

```php
paginate(
    User|int $owner,
    array $filters = [],
    int $perPage = 50,
): LengthAwarePaginator

forTask(
    User|int $owner,
    Task|int $task,
): Collection
```

`paginate()`:

- applies `ownedBy($owner)` before all other predicates;
- supports `project_id`, `task_id`, `event_type`, `from`, and `until` filters;
- treats `from` as inclusive and `until` as exclusive UTC bounds;
- accepts a `TaskActivityType` or its backed string for `event_type`;
- eager-loads `project` and `task` with the task's soft-deleted row included;
- orders by `created_at DESC, id DESC` for deterministic newest-first feeds;
- defaults to 50 results per page and never interpolates a filter into SQL.

`forTask()` uses the same owner scope and historical task relation, orders by
`created_at ASC, id ASC`, and returns the complete chronological timeline for
that task. A later screen query may add pagination or date filters without
changing the recorder contract.

Foreign-owned projects, tasks, and activity rows therefore produce no feed
results. A soft-deleted owned task remains available through an activity row
so its history is readable without returning the task to active work views.

## Error and transaction behavior

The recorder does not open a separate transaction. The calling mutation
Action owns the transaction that changes task state and creates its activity
row, so a failed mutation cannot leave behind a history event. DYX-006 and
later Actions will call it inside their existing transaction boundaries.

The recorder rejects an unsaved task because it cannot produce valid
historical context. Normal Laravel validation and database constraints remain
responsible for maximum field lengths and foreign-key integrity.

## Test contract

Write tests before the implementation and keep them split by responsibility:

### `tests/Unit/Domain/Activity/TaskActivityRecorderTest.php`

- copies the task's user/project/task ids into a newly recorded row;
- persists backed enum values as stable strings in raw JSON and model casts;
- wraps field-based scalar status/priority values into readable objects;
- redacts title and description old/new values from `TASK_UPDATED` events;
- removes title/description keys from metadata while preserving
  `metadata.is_reopen = true`;
- recursively normalizes nested enums and UTC date-time values;
- rejects an unsaved task with a clear exception;
- prevents updating or deleting an existing `TaskActivity` model.

### `tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php`

- returns only the authenticated owner's activity rows;
- hides foreign project/task filters even when their ids are supplied;
- applies event and UTC-bound filters with deterministic newest-first order;
- includes a soft-deleted task's context in historical results;
- defaults global pagination to 50 rows;
- returns a task timeline oldest-first and still scopes it by owner.

No browser test is required for DYX-004 because it introduces no route or
user-facing control. Browser and accessibility coverage begins when DYX-010
connects the feed to screens.

## Explicit non-goals

This design does not add:

- task/project Actions that call the recorder;
- Eloquent observers, domain event buses, or database triggers;
- global or project activity routes and views;
- dashboard or analytics calculations;
- activity editing, deletion, or restoration controls;
- title/description snapshots or arbitrary form-body logging;
- task movement between projects in v1;
- queues, schedulers, WebSockets, external search, or visual design work.

## Review checklist

- The write boundary receives task context rather than caller-owned ids.
- Enum, date, metadata, and sensitive-text rules are explicit and testable.
- Existing activity history survives task soft deletion.
- The read boundary scopes by user before applying filters.
- UTC period bounds are accepted without making DYX-004 responsible for
  timezone resolution.
- Later task Actions can use the service inside one transaction.
- No UI or P2 entity is introduced ahead of the dependency order.
