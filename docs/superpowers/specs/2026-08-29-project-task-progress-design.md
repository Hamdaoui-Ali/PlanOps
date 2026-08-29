# Project Tasks and Derived Progress Design

## Goal

Make project progress understandable and actionable by giving every project
its own task list and deriving progress from the project's top-level tasks.
Users should be able to add work, update task status, and immediately see the
project percentage change without entering a separate progress value.

## Product decisions

### Top-level tasks are the progress denominator

Project progress is calculated from top-level tasks only:

```text
completed top-level tasks / eligible top-level tasks * 100
```

Eligible tasks are top-level tasks whose status is not `CANCELLED`.
Subtasks remain useful for breaking down work and display their own local
completion state, but never add weight to the project percentage. A project
with no eligible tasks displays `0%` and `No active scope`.

Project lifecycle status remains manual. Completing all tasks does not change
the project's status automatically.

### Replace duplicate progress concepts

The Projects index currently shows `Scope progress` and `Progress`, although
both use the same value. The index will show:

- `Tasks`: completed eligible top-level tasks out of total eligible tasks;
- `Progress`: the derived percentage.

Helper text will explain that cancelled tasks are excluded and subtasks do not
change the project percentage.

## User flow

1. The user opens a project from the Projects index.
2. The project overview shows project identity, manual lifecycle status,
   target date, task counts, and a progress bar.
3. The user selects `New task`, supplies a title, and optionally sets status,
   priority, due date, description, or a parent task.
4. The task appears in the project task list with a project-local key.
5. The user changes a top-level task status to `DONE` or another workflow
   state using a visible, keyboard-operable control.
6. The overview and Projects index calculate the new progress on the next
   response. No manually editable progress field exists.

## Route and domain boundaries

Add the missing project overview route:

- `GET /projects/{project}` → project overview and task list.

Keep the existing task creation routes:

- `GET /projects/{project}/tasks/create`;
- `POST /projects/{project}/tasks`.

After successful task creation, redirect to the project overview so the user
can see the new task and updated counts. Existing owner-scoped project binding
continues to protect project access.

The overview query loads the owned project with its non-deleted tasks ordered
for scanning, while the existing aggregate query remains the source of truth
for eligible and completed top-level counts. The progress formula stays in
the Project model/query contract and is not duplicated in Blade templates.

## Screen design

### Projects index

Keep the existing desktop ledger and replace the duplicate column. Each row
shows project name/key, manual status, task count, progress, target date, and
an `Open project` action. The progress bar has a text alternative and an
accessible name containing the project name and percentage.

### Project overview

Use the existing PlanOps console visual language: dark/light theme support,
clear typography, restrained borders, lime primary action, teal links, and
visible focus states.

The page contains:

- a header with project name, key, status, target date, and `Edit project`;
- a progress summary showing `x of y tasks done`, percentage, and the rule;
- a `Current work` or task list surface;
- a `New task` primary action;
- rows with task key, title, status, priority, due date, and subtask count;
- a useful empty state: `This project has no tracked work yet. Add the first
  task.`

Task status is shown as text plus visual treatment. Status changes use a
standard form/select control, not drag-only interaction. The layout remains
usable on desktop and collapses to a list-first presentation at narrower
widths.

## Error and empty behavior

- Invalid task input returns to the task form with field-level errors and
  preserves safe old values.
- A task cannot be created for another user's project because of the existing
  owner-scoped route binding and policy boundary.
- A project with no tasks shows `0%`, `0 of 0 tasks done`, and `No active
  scope` rather than implying that work is complete.
- A project with only cancelled top-level tasks uses the same no-active-scope
  state.
- A task list never displays soft-deleted tasks.

## Test contract

Add or extend feature tests to prove:

- the project overview renders only the owner's project and its tasks;
- a valid task creation redirects to the project overview;
- a task is attached to the selected project;
- `0%`, partial, and `100%` progress values are derived from eligible
  top-level tasks;
- subtasks do not change the project percentage;
- cancelled top-level tasks are excluded;
- the Projects index exposes task counts and one unambiguous progress value;
- unauthorized project access remains unavailable.

PHP/Pest execution must be reported honestly if the local PHP executable is
still unavailable; static checks and the frontend build remain useful
verification gates.

## Non-goals

- no manually entered project-progress field;
- no weighting by estimates, priority, time, or subtask count;
- no automatic project status transitions;
- no new analytics model or background worker;
- no redesign of unrelated dashboard, board, or global work screens.

## Acceptance criteria

1. Every project can display and create its own tasks.
2. A user can change task status from the project interface using a visible
   non-drag control.
3. Progress is always a derived 0–100% value based on completed eligible
   top-level tasks.
4. Subtasks and cancelled tasks do not distort the project percentage.
5. The Projects index no longer presents two identical progress attributes.
6. Empty, validation, ownership, and keyboard/focus states are clear.
