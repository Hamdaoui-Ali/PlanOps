# Project Board Design

## Goal

Add an accessible project board that lets the owner see top-level work by
status and move tasks through the existing fixed workflow without requiring
drag-and-drop.

## Product decisions

- The board is project-scoped and only shows the authenticated owner's
  non-deleted, top-level tasks.
- The default columns are `BACKLOG`, `NOT_STARTED`, `IN_PROGRESS`,
  `IN_REVIEW`, `BLOCKED`, and `DONE`. `CANCELLED` is available through an
  explicit filter and is hidden by default.
- Cards show the task display key, title, readable status, priority, due date,
  labels, and direct-subtask progress. Each card links to the existing task
  detail route.
- Every card exposes a labelled status control and submit action. This is the
  complete keyboard and mobile path; drag-and-drop is optional enhancement,
  not a required interaction.
- Status changes reuse `ChangeTaskStatus`, preserving existing lifecycle
  timestamps and activity recording. Same-column ordering uses a new
  transactional `ReorderTasks` action and explicit ordered task ids.
- Subtasks never appear as board cards and never affect project progress.

## Architecture

Create `ProjectBoardQuery` to return an immutable board data structure grouped
by `TaskStatus`, with tasks eager-loaded for project, labels, and bounded
direct-child progress. The query must scope by both project ownership and
top-level task status, exclude soft-deleted records, and use deterministic
`position`, then `number`, ordering.

Create `ReorderTasks` with the contract:

```php
public function handle(
    User $owner,
    Project $project,
    TaskStatus $status,
    array $orderedTaskIds,
): void;
```

It must verify that every id belongs to the owner/project, is top-level,
non-deleted, and already has the supplied status. Any mismatch fails without
partial updates; valid ids receive contiguous positions in one transaction.

Add a project-board controller boundary and routes for the board read and
reorder request. Status moves continue through the existing task status
endpoint and redirect back to the board with a readable flash message.

## UI and accessibility

Use the existing PlanOps console language: open canvas, restrained borders,
lime primary actions, teal links, readable text statuses, and visible focus.
Render a responsive horizontal board on desktop/tablet and a list-first
stacked column layout on mobile. Keep cancelled filtering as a labelled GET
control. Every card has a visible `Move to...` control, a task link, and no
essential icon-only action. Respect reduced-motion preferences; no interaction
depends on hover.

## Error and empty behavior

- Foreign or invalid project/task ids resolve as unavailable.
- Invalid reorder payloads return validation errors without changing order.
- A project with no eligible tasks shows an actionable empty state linking to
  task creation.
- A column with no tasks states `No tasks in this status.`
- Cancelled tasks are only shown when the user explicitly enables the filter.

## Test contract

Add unit coverage for board grouping and reorder invariants. Add feature
coverage for owner scoping, cancelled filtering, card metadata, task-detail
links, status-control accessibility, empty states, and redirect behavior.
Add browser coverage when the browser harness is available for keyboard
status movement, visible focus, responsive layout, and the optional drag path.

## Non-goals

- No custom workflow editor.
- No recursive subtask rendering.
- No drag-only status or ordering behavior.
- No new client-side framework or persistence layer.
- No changes to project progress semantics.

## Acceptance criteria

1. An owner can open a project board and see eligible top-level tasks grouped
   into the six default workflow columns.
2. A task can be moved to another status using its labelled non-drag control.
3. A same-column reorder is validated and persisted transactionally.
4. Foreign, deleted, child, and cancelled-by-default tasks are excluded as
   specified.
5. Cards expose readable status and priority text, visible focus, keyboard
   operation, and task-detail links.
