# DYX-003 Foundation Design

Date: 2026-08-27

Status: approved for implementation planning

## Goal

Create the persistence and domain foundation required by the rest of PlanOps:

- the seven-table relational model;
- typed domain enums;
- Eloquent models and ownership relationships;
- deterministic factories and seed data;
- executable schema, enum, and ownership invariants.

This slice is deliberately limited to domain structure. It does not add
project or task mutation Actions, HTTP routes, policies, dashboard queries, or
new user-facing screens. Those features consume this foundation in later
dependency-ordered slices.

## Context and constraints

The repository is a Laravel 13 modular monolith using PHP 8.3, PostgreSQL in
production, Blade/Tailwind/Vite for the application surface, and Pest/PHPUnit
for tests. The existing DYX-002 work already provides authenticated users,
the application shell, and a `user_preferences` migration/model.

The production schema is PostgreSQL-first. Test migrations must remain usable
with the repository's SQLite in-memory test configuration. PostgreSQL `jsonb`
columns are used for activity payloads; Laravel's SQLite grammar provides the
test-compatible representation.

All user-owned reads remain explicitly scoped. Foreign keys protect basic
relationships, while same-owner and same-project rules remain application
invariants for later Actions and policies.

## Chosen approach

Add the five missing domain tables and align the existing preferences table
with the timestamp contract. Keep one model per domain entity, use backed PHP
enums for persisted state, and expose simple ownership scopes in addition to
the inverse Eloquent relationships.

This is preferred over an early repository abstraction because the product is
a small relational application and the spec calls for direct, testable Laravel
models. It is also preferred over putting all rules in database triggers because
cross-record rules such as same-owner parentage need readable application-level
errors and policy checks.

## Persistence model

### `user_preferences`

Keep the existing table and migration identity. Add enum casts at the model
level and use `timestampsTz()` so all real timestamps follow the UTC-capable
timestamp contract. Retain the unique `user_id` foreign key and documented
defaults:

- `timezone`: `Africa/Casablanca`;
- `week_start_day`: `MONDAY`;
- `theme`: `SYSTEM`;
- `density`: `COMFORTABLE`.

### `projects`

Columns:

`id`, `user_id`, `name`, `key`, `description`, `status`, `color`, `icon`,
`start_on`, `target_on`, `next_task_number`, `archived_at`, `created_at`, and
`updated_at`.

Rules and indexes:

- `key` is stored uppercase and is unique per user;
- `UNIQUE(user_id, key)`;
- `next_task_number` is a non-null unsigned big integer with default `1`;
- `start_on` and `target_on` are date-only values;
- `archived_at` is a timezone-aware nullable timestamp;
- indexes cover `(user_id, status)`, `(user_id, archived_at)`, and
  `(user_id, updated_at)`.

Project lifecycle mutations remain manual and are implemented by DYX-005.
Archiving is represented by `archived_at`; it does not delete tasks or
activity.

### `tasks`

Columns:

`id`, `user_id`, `project_id`, `parent_task_id`, `number`, `title`,
`description`, `status`, `priority`, `due_on`, `position`, `first_started_at`,
`completed_at`, `cancelled_at`, `status_changed_at`, `created_at`,
`updated_at`, and `deleted_at`.

Rules and indexes:

- `UNIQUE(project_id, number)`;
- `number` is an unsigned big integer;
- `position` is a non-negative integer with default `0`;
- `due_on` is a date-only value;
- real lifecycle timestamps use timezone-aware columns;
- `status_changed_at` is required and defaults to the creation time;
- `deleted_at` provides soft deletion;
- indexes cover `(user_id, status)`, `(project_id, status, position)`,
  `(project_id, parent_task_id)`, `(user_id, priority)`, `(user_id, due_on)`,
  `(user_id, updated_at)`, and `(parent_task_id)`.

The self-reference is structurally allowed by the database but application
rules later enforce one level of hierarchy, same-project parentage, and no
self-parenting. v1 does not support moving a task between projects.

The project foreign key is restrictive for physical deletion so historical
tasks cannot disappear through an accidental project delete. Normal project
archive is non-destructive. The parent foreign key nulls on physical parent
deletion; normal task deletion is soft and therefore retains the relationship.

### `labels`

Columns:

`id`, `user_id`, `name`, `normalized_name`, `color`, `created_at`, and
`updated_at`.

Rules and indexes:

- `UNIQUE(user_id, normalized_name)`;
- both the display name and normalized name are bounded strings;
- the unique constraint also supplies the primary lookup index.

Normalization and label Actions are implemented by DYX-007. A label delete
detaches pivot rows without deleting tasks.

### `task_label`

Use a composite unique key on `task_id` and `label_id`, plus `created_at`.
There is no synthetic primary key. Both foreign keys cascade only the pivot
association, never the task itself.

### `task_activities`

Columns:

`id`, `user_id`, `project_id`, `task_id`, `event_type`, `field`, `old_value`,
`new_value`, `metadata`, and `created_at`.

Rules and indexes:

- `event_type` is a bounded string backed by `TaskActivityType`;
- `old_value`, `new_value`, and `metadata` are nullable JSONB columns;
- indexes cover `(user_id, created_at)`, `(project_id, created_at)`,
  `(task_id, created_at)`, and `(event_type, created_at)`;
- there is no `updated_at` column;
- the model exposes relationships and casts but no application update/delete
  operation.

The activity recorder and stronger append-only enforcement are DYX-004
responsibilities. Activity rows reference their owning task and project and
are not removed by ordinary task soft deletion.

## Domain enums

Create these backed string enums using the exact persisted values from the
spec:

- `ProjectStatus`: `PLANNED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, `CANCELLED`;
- `TaskStatus`: `BACKLOG`, `NOT_STARTED`, `IN_PROGRESS`, `IN_REVIEW`,
  `BLOCKED`, `DONE`, `CANCELLED`;
- `TaskPriority`: `LOW`, `MEDIUM`, `HIGH`, `URGENT`;
- `TaskActivityType`: `TASK_CREATED`, `TASK_UPDATED`, `STATUS_CHANGED`,
  `PRIORITY_CHANGED`, `DUE_DATE_CHANGED`, `LABEL_ADDED`, `LABEL_REMOVED`,
  `SUBTASK_CREATED`, `TASK_MOVED_PROJECT`, `TASK_DELETED`, `TASK_RESTORED`;
- `ThemePreference`: `SYSTEM`, `LIGHT`, `DARK`;
- `DensityPreference`: `COMFORTABLE`, `COMPACT`;
- `WeekStartDay`: `MONDAY`, `SUNDAY`.

`TaskStatus::category()` is the first required derived enum behaviour:

| Category | Statuses |
| --- | --- |
| `PLANNED` | `BACKLOG`, `NOT_STARTED` |
| `ACTIVE` | `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED` |
| `TERMINAL` | `DONE`, `CANCELLED` |

`OVERDUE` is not an enum value. It remains a date-and-status calculation for
the task metadata slice.

## Model boundaries and relationships

### `User`

Extend the existing authenticated model with `projects()`, `tasks()`,
`labels()`, and `taskActivities()` relations. Keep `preference()` unchanged.

### `Project`

Owns a `User`, has many `Task` records, and has many `TaskActivity` records.
It casts `status` to `ProjectStatus` and date fields to immutable dates. An
ownership scope accepts a user or user id and is used by later query/action
code.

### `Task`

Belongs to `User`, `Project`, and optionally a parent `Task`; has many child
tasks and activities; and belongs to many labels through `task_label` with
pivot timestamps. It uses `SoftDeletes`, casts status/priority to backed
enums, and casts date/timestamp fields to immutable Carbon values. Its
ownership scope is separate from its project relation so user scoping remains
explicit in queries.

### `Label`

Belongs to `User` and belongs to many tasks through the pivot. It exposes the
same explicit ownership scope used by project/task queries.

### `TaskActivity`

Belongs to `User`, `Project`, and `Task`. It casts `event_type` to
`TaskActivityType` and the three JSON payload columns to arrays. It is created
through the future recorder and has no mutation Action in this slice.

## Factory and seed contract

Factories must make valid ownership the easy path:

- `UserPreferenceFactory` uses the four documented defaults and supports
  alternate timezone/theme/density states;
- `ProjectFactory` creates valid keys and exposes one state per project
  lifecycle value;
- `TaskFactory` supports top-level tasks, subtasks, every status and priority,
  deleted tasks, and reopened-task timestamp states while keeping user and
  project ownership aligned;
- `LabelFactory` creates normalized user-owned labels;
- `TaskActivityFactory` derives user/project/task context and supports each
  activity type.

`DatabaseSeeder` creates a small deterministic demonstration dataset with
users in different timezones, projects in each lifecycle state, top-level
tasks, subtasks, labels, activity rows, one soft-deleted task, and one task
whose fixture history represents a reopen. It must not introduce deferred
models such as teams, assignees, timers, comments, or integrations.

## Test contract

Tests are written before production implementation and run against the existing
SQLite in-memory configuration:

1. `SchemaInvariantTest` verifies all seven tables, unique constraints,
   soft-delete support, JSON payload columns, timestamp/date types where the
   test grammar exposes them, and the documented indexes.
2. `TaskStatusTest` verifies the complete status set and the three category
   mappings, including terminal separation of `DONE` and `CANCELLED`.
3. `OwnershipScopeTest` creates two users and proves each user's project,
   task, label, and activity queries exclude the other user's records. It also
   verifies task/project and activity/task relationship consistency for valid
   fixtures.

The tests do not pretend that database foreign keys alone prove same-owner
invariants. Cross-owner mutation rejection is tested when the corresponding
Actions and policies are introduced.

## Commit boundaries

Because the repository is intended to show several reviewable commits, the
implementation will be split into logical commits:

1. failing DYX-003 schema and enum tests;
2. migrations and backed enums;
3. models, casts, relationships, and ownership scopes;
4. factories, deterministic seeding, and green-test cleanup.

Each commit must pass the checks appropriate to its stage, and the final slice
must pass the complete DYX-003 verification gate before moving to DYX-004.

## Explicit non-goals

This design does not add:

- generic repositories or service containers;
- project/task HTTP routes;
- project/task mutation Actions;
- authorization Policies;
- activity recording behaviour beyond the model contract;
- dashboard or analytics queries;
- full-text search;
- queues, schedulers, WebSockets, teams, assignees, time tracking, or AI.
