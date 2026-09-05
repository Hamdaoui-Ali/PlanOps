# PlanOps domain contracts

> **Historical baseline notice (2026-09-05):** This document describes the pre-collaboration domain. The Sprint 2 authority in [`docs/PlanOps_Sprint_2.md`](../PlanOps_Sprint_2.md) supersedes conflicting ownership, project-key, label-scope, role, invitation, assignment, actor, and notification claims. Non-conflicting task, status, activity, analytics, and accessibility contracts remain applicable.

## Persistence model

The seven core tables are `users`, `user_preferences`, `projects`, `tasks`, `labels`, `task_label`, and `task_activities`.

| Table | Contract |
| --- | --- |
| `users` | Laravel-authenticated owner of all PlanOps data. |
| `user_preferences` | One row per user: IANA `timezone`, `week_start_day`, `theme`, and `density`. |
| `projects` | User-owned work context: name, per-user unique uppercase key, manual lifecycle status, dates, archive timestamp, and `next_task_number >= 1`. |
| `tasks` | User-owned project work item with optional one-level `parent_task_id`, project-local `number`, workflow state, priority, date-only due date, ordering position, lifecycle timestamps, and soft-delete timestamp. |
| `labels` | User-owned normalized label name and optional color. |
| `task_label` | Unique task/label association; deleting a label detaches it without deleting tasks. |
| `task_activities` | Append-only `TaskActivity` audit record with user, project, task, event type, optional field, JSONB old/new values, JSONB metadata, and creation timestamp. It is never editable in the UI. |

Key constraints: `UNIQUE(user_id, projects.key)`, `UNIQUE(project_id, tasks.number)`, `UNIQUE(user_id, labels.normalized_name)`, and `UNIQUE(task_id, label_id)`. A project key matches `^[A-Z0-9]{2,10}$`; task position is non-negative; a task cannot be its own parent.

## Backed enums and semantics

PHP backed enums are `ProjectStatus`, `TaskStatus`, `TaskPriority`, `TaskActivityType`, `ThemePreference`, `DensityPreference`, and `WeekStartDay`.

- `ProjectStatus`: `PLANNED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, `CANCELLED`. Project lifecycle is user-controlled; completing tasks never changes it automatically.
- `TaskStatus`: `BACKLOG`, `NOT_STARTED`, `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, `DONE`, `CANCELLED`. Categories are planned (Backlog, Not Started), active (In Progress, In Review, Blocked), and terminal (Done, Cancelled).
- `TaskPriority`: `LOW`, `MEDIUM`, `HIGH`, `URGENT`; default `MEDIUM`.
- `TaskActivityType`: `TASK_CREATED`, `TASK_UPDATED`, `STATUS_CHANGED`, `PRIORITY_CHANGED`, `DUE_DATE_CHANGED`, `LABEL_ADDED`, `LABEL_REMOVED`, `SUBTASK_CREATED`, `TASK_MOVED_PROJECT`, `TASK_DELETED`, `TASK_RESTORED`.
- `ThemePreference`: `SYSTEM`, `LIGHT`, `DARK`; `DensityPreference`: `COMFORTABLE`, `COMPACT`; `WeekStartDay`: `MONDAY`, `SUNDAY`.

`OVERDUE` is computed, never a status: local user date is after `due_on` and the task is not Done or Cancelled. Entering Done sets `completed_at` and clears `cancelled_at`; entering Cancelled does the opposite; reopening clears the current terminal timestamp. `first_started_at` is set only the first time a task enters In Progress.

## Ownership, hierarchy, and identity invariants

- Every Project belongs to exactly one user. Every Task has the same user as its project and, if present, its parent. Every label assignment joins a task and label owned by the same user. Every TaskActivity references the owner, its task, and that task's project.
- Top-level tasks have no parent; a subtask has a top-level parent in the same project. No nesting beyond Project -> Task -> Subtask is allowed.
- Task numbers are allocated inside a transaction by locking the project, reading `next_task_number`, creating the task, incrementing the counter, and committing. Numbers are never reused, including after soft deletion.
- The stable display key is `{PROJECT_KEY}-{TASK_NUMBER}`. A project key is effectively immutable after its first task exists. In v1, tasks do not move between projects; task identifiers therefore remain stable historical references.
- Soft-deleted tasks are hidden from active views and normal progress reporting while their history remains meaningful.

## Application action signatures

Actions are use-case boundaries, not generic CRUD. The implementation may use DTOs, but preserves these semantic signatures:

```php
CreateProject::handle(User $user, CreateProjectData $data): Project
UpdateProject::handle(Project $project, UpdateProjectData $data): Project
ChangeProjectStatus::handle(Project $project, ProjectStatus $status): Project
ArchiveProject::handle(Project $project): Project
RestoreProject::handle(Project $project): Project

CreateTask::handle(Project $project, CreateTaskData $data): Task
UpdateTask::handle(Task $task, UpdateTaskData $data): Task
ChangeTaskStatus::handle(Task $task, TaskStatus $status): Task
ChangeTaskPriority::handle(Task $task, TaskPriority $priority): Task
ChangeTaskDueDate::handle(Task $task, ?CarbonImmutable $dueOn): Task
DeleteTask::handle(Task $task): void
RestoreTask::handle(Task $task): Task
CreateSubtask::handle(Task $parent, CreateTaskData $data): Task
ReorderTask::handle(Project $project, TaskStatus $status, ReorderTaskData $data): void

AttachLabelToTask::handle(Task $task, Label $label): void
DetachLabelFromTask::handle(Task $task, Label $label): void
CreateLabel::handle(User $user, CreateLabelData $data): Label
DeleteLabel::handle(Label $label): void
TaskActivityRecorder::record(Task $task, TaskActivityType $type, ?string $field, mixed $oldValue, mixed $newValue, array $metadata = []): TaskActivity
ProjectProgressCalculator::calculate(Project $project): ProjectProgress
```

`TaskActivityRecorder` normalizes payloads, attaches the owner/project/task context, avoids unnecessary sensitive text, and uses stable metadata conventions. It records meaningful user domain changes, not page opens, filters, cache refreshes, or component rerenders. A reopen is a `STATUS_CHANGED` payload with old terminal status, new non-terminal status, and metadata identifying the reopen.

## Transaction boundaries and derived metrics

Use one database transaction for every multi-record operation. Creating a task allocates its number, creates it, and records `TASK_CREATED`; changing status updates state/timestamps and records `STATUS_CHANGED`; board reordering updates all affected positions as one unit. Cross-project movement is not a v1 operation; the retained `TASK_MOVED_PROJECT` activity type is reserved for a future, carefully-defined migration transaction.

Project progress is derived, never persisted:

```text
eligible_tasks = top-level tasks where status != CANCELLED
completed_tasks = eligible top-level tasks where status = DONE
project_progress = completed_tasks / eligible_tasks × 100
```

When no eligible tasks exist, display `0%` and `No active scope`. Subtasks have their own non-cancelled completion ratio and never automatically change the parent or project status.

Period analytics are user-timezone bounded. `Created`, `Completed`, `Started`, `Moved to Review`, `Became Blocked`, and `Reopened` count distinct top-level tasks according to creation or `STATUS_CHANGED` events. Created-vs-completed balance is scope flow, never a productivity score. The heatmap is labeled `Tracked Work Activity`, not Productivity; no metric may claim hours worked or productivity without an actual-effort source.
