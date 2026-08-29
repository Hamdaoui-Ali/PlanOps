# My Work Design

## Goal

Replace the temporary `/my-work` screen with a cross-project execution view
that helps the owner focus on active work while keeping filtering and sorting
predictable.

## Product decisions

- The default view groups owned, non-deleted tasks into `IN_PROGRESS`,
  `IN_REVIEW`, `BLOCKED`, and `NOT_STARTED` sections, in that order.
- `BACKLOG`, `DONE`, and `CANCELLED` remain reachable through an explicit
  status filter; cancelled tasks are not included by default.
- Results are paginated at 50 tasks and include project identity, stable task
  key, labels, direct-subtask summary, and last updated timestamp.
- Each row links to the existing task detail route and exposes labelled quick
  actions for status and priority. Actions preserve the user's current filter
  context when redirecting back to `/my-work`.
- Filters are project, status, priority, label, due state, created date, and
  updated date. Due shortcuts are overdue, due today, due this week, and no
  due date.
- Sorting defaults to recently updated. Supported alternatives are recently
  created, priority, due date, task key, and project. Sort values are mapped
  from a fixed allow-list and never become raw SQL column input.

## Architecture

Create `MyWorkQuery` as the single read boundary for cross-project tasks. It
must scope by owner before applying any filter, eager-load `project`,
`labels`, and bounded direct-child counts, exclude soft-deleted tasks, and
return a 50-item paginator plus the normalized filter/sort state needed by the
view.

Create `MyWorkFiltersRequest` to validate and normalize query parameters. It
must accept only the documented status, priority, due-state, and sort values;
project and label ids must be owner-scoped in the query rather than trusted
from the request. Date filters use date-only values and the user's local date
for due shortcuts.

Create `MyWorkController@index` to pass the paginator, owned project and label
filter options, statuses, priorities, and current query parameters to the
Blade view. Existing task mutation actions remain the source of truth for
quick status and priority changes; the controller's successful redirects
return to `/my-work` with the safe current query string.

## UI and accessibility

Use the selected status-section layout: a compact filter toolbar followed by
one section per visible status. Each section has a clear heading and count.
Rows use the current PlanOps console language and display text status and
priority, never color alone. Filter controls have explicit labels, the reset
filters action is visible when filters are active, focus rings remain visible,
and every quick action works with keyboard and without hover or drag.

On mobile, each row stacks its metadata and actions without horizontal clipping.
On tablet and desktop, the sections use a scan-friendly table-like grid.
Respect the existing theme, density, and reduced-motion settings.

## Error and empty behavior

- Invalid filter values return validation errors without executing an unsafe
  query.
- Foreign project or label ids produce no matching results and never reveal
  another user's names or tasks.
- No matching tasks shows `No tasks match these filters.` and a visible
  `Reset filters` action.
- A user with no tasks shows `No tracked work yet.` and a link to Projects.
- A section with no tasks is omitted unless it is one of the four default
  focus sections; default empty sections show `No tasks in this status.`.
- Task mutations preserve a safe return URL containing only allow-listed
  filters and sort values.

## Test contract

Add feature coverage for default section ordering, strict ownership, soft
deletion, pagination, every filter, due shortcut semantics, every sort option,
invalid-input rejection, empty states, task-detail links, and quick-action
redirect context. Add browser coverage when a browser harness is available
for keyboard filtering, reset, status/priority actions, focus visibility, and
mobile readability.

## Non-goals

- No saved filters or custom views.
- No full-text search; global search is a later feature.
- No drag-and-drop or client-side persistence.
- No automatic status changes, progress changes, or productivity scoring.
- No project task-list screen in this tranche.

## Acceptance criteria

1. `/my-work` shows the owner's active work grouped by status with useful
   counts and an actionable empty state.
2. Filters and due shortcuts return only matching owned, non-deleted tasks.
3. All supported sorts are deterministic and cannot inject SQL identifiers.
4. Rows expose readable metadata, task-detail links, and keyboard-operable
   quick actions.
5. Mutations return safely to the current `My Work` context.
