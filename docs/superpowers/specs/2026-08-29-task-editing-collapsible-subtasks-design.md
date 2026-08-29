# Task Editing and Collapsible Subtasks Design

## Goal

Make every project task and subtask visible, editable, and understandable
without making the project overview too dense.

## Product decisions

### One task model, one editing surface

Top-level tasks and subtasks use the same task detail interface. A task can be
opened from the project overview and edited through the existing domain
boundaries for title, description, status, priority, and due date.

The task detail surface must show:

- task key and title;
- parent project;
- parent task, when the task is a subtask;
- status;
- priority;
- due date;
- description;
- subtasks belonging directly to the task;
- readable activity history.

No task field is silently editable in the project progress calculation. Project
progress remains based on completed eligible top-level tasks only.

### Collapsible subtasks in the project overview

The project overview remains top-level-task-first. Each top-level task row is
shown in the main list. If it has direct subtasks, the row includes a
`Show subtasks` button and a summary such as `2 of 4 subtasks done`.

When expanded, subtasks appear immediately below the parent in an indented
child region. Each child row shows:

- task key;
- title;
- status;
- priority;
- due date;
- `Edit` action.

The child region is collapsed by default. Expanding one parent does not expand
other parents. The expanded state is a client-side presentation preference and
does not change persistence or task state.

### Accessibility behavior

The expand control is a real button, not a clickable table row. It must:

- expose `aria-expanded="false|true"`;
- reference the child region with `aria-controls`;
- use visible text that changes between `Show subtasks` and `Hide subtasks`;
- remain keyboard operable with Enter and Space;
- preserve a visible focus indicator;
- announce the number of subtasks in accessible text.

The child region uses a stable id derived from the parent task id. Status,
priority, and due-date controls include explicit labels and text values, so
color is never the only state signal.

## User flow

1. The user opens a project overview.
2. Top-level tasks are listed with status, priority, due date, and subtask
   summary.
3. The user selects `Show subtasks` on a parent task.
4. Direct subtasks slide or appear below that parent, visibly indented.
5. The user selects `Edit` on either the parent or a subtask.
6. The task detail interface opens for that exact task.
7. The user edits title/description in the main form and can update status,
   priority, and due date through labelled controls.
8. Saving returns to that task's detail surface with a visible status
   message.
9. Returning to the project overview preserves the task hierarchy and shows
   the latest values.

## Route and domain boundaries

Add task detail routes:

- `GET /tasks/{task}` → task detail;
- `PATCH /tasks/{task}` → title and description update;
- `PATCH /tasks/{task}/priority` → priority update;
- `PATCH /tasks/{task}/due-date` → due-date update;
- `DELETE /tasks/{task}` → soft delete.

Reuse the existing `UpdateTask`, `ChangeTaskPriority`, `ChangeTaskDueDate`,
and `DeleteTask` actions. Add controller/request boundaries and keep the
existing owner-scoped task binding. Every successful mutation redirects to
the task detail page with a readable message; the detail page has a clear
`Back to project` link.

The project overview query must load direct children for each top-level task,
excluding soft-deleted children. It must not recursively load arbitrary depth:
the supported display hierarchy is parent plus direct subtasks. A subtask may
not become a parent of another subtask through the existing creation safety
rules.

## Task status, priority, and due date

Each task and subtask has independent values for:

- status from the fixed `TaskStatus` workflow;
- priority from `TaskPriority`;
- optional date-only due date.

Changing a subtask does not automatically change the parent status. Changing a
parent does not automatically change child statuses. Activity records remain
task-specific and append-only.

Subtask summaries count direct, non-deleted children. A cancelled child may be
shown in the expanded list with its `Cancelled` status but is excluded from the
subtask completion denominator. The project percentage continues to exclude
all subtasks.

## Visual and interaction design

Use the current PlanOps console language: open canvas, restrained borders,
lime primary action, teal links, visible focus, and compact but readable
desktop density.

The project overview uses a tree-like table treatment:

- parent rows have normal emphasis;
- child rows have indentation, a subtle left rule, and a muted background;
- the expand button sits beside the parent task identity;
- `Edit` is visible text, not an icon-only action;
- no essential interaction depends on hover or drag-and-drop.

On smaller screens, child rows remain readable in a stacked layout. The
expand button and edit controls retain comfortable target sizes.

## Error and empty behavior

- Invalid task updates return to the detail form with field-specific errors
  and preserved safe input.
- A foreign task URL resolves as unavailable through owner-scoped binding.
- A deleted task is not shown in the project overview or editable detail route.
- A parent with no subtasks has no expand control.
- A parent whose children are all cancelled still shows its child count and
  the explicit cancelled statuses, but its completion summary says `No active
  subtasks`.
- If a task has no activity, show `No activity recorded yet.`

## Test contract

Add or extend tests to prove:

- top-level tasks load their direct subtasks in the project overview;
- soft-deleted children are hidden;
- parent and child rows expose their own keys, status, priority, and due date;
- the collapsed state uses an accessible button and child region contract;
- each task can open the same detail route;
- title/description, priority, due date, and delete actions authorize the
  owner and persist through existing actions;
- foreign task detail and mutation requests are unavailable;
- child status/priority/due-date changes do not mutate the parent;
- subtask summary excludes cancelled children and project progress remains
  top-level-only.

## Non-goals

- no drag-and-drop hierarchy editing;
- no recursive nesting beyond one parent and direct subtasks;
- no automatic parent/child status synchronization;
- no subtask weighting in project progress;
- no new time tracking or estimate fields;
- no new JavaScript framework or client-side persistence layer.

## Acceptance criteria

1. A user can open and edit any owned top-level task.
2. A user can expand a parent task and see its direct subtasks.
3. A user can open and edit any visible subtask independently.
4. Parent and child tasks each display independent status, priority, and due
   date values.
5. Expand/collapse is keyboard accessible and communicates state to assistive
   technology.
6. Project progress still uses only completed eligible top-level tasks.
