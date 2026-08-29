# Project Task List Design

## Goal

Add a dedicated `/projects/{project}/tasks` list so the owner can scan and
update every task in one project without relying on the overview hierarchy or
the status-oriented board.

## Product decisions

- The list is project-scoped and includes owned, non-deleted top-level tasks
  and direct subtasks. It never displays another user's records.
- Each row shows task key, title, status, priority, due date, labels, direct
  subtask summary, and last updated timestamp.
- Every task and subtask links to the existing `/tasks/{task}` detail route.
- Local filters are status, priority, label, and due state. Due shortcuts are
  overdue, due today, due this week, and no due date.
- Sorting supports recently updated, recently created, priority, due date,
  and task key, with recently updated as the default. Sort values use a fixed
  allow-list and deterministic id tie-breaking.
- Rows expose labelled quick actions for status and priority. Existing domain
  actions remain the source of truth, and redirects return to this project
  list with only safe filter/sort context.
- Project progress remains unchanged: only completed eligible top-level tasks
  contribute to the percentage.

## Architecture

Create `ProjectTaskListQuery` as a project-owned read boundary. It receives a
`User`, an owner-scoped `Project`, normalized filters, and a 50-item page size;
it eager-loads project, labels, parent, and bounded child aggregates, excludes
soft-deleted rows, and returns a paginator. The query must preserve the
one-level hierarchy in display data without recursively loading grandchildren.

Reuse the existing `MyWorkFiltersRequest` rules where possible, but create a
project-list request or normalization method that omits cross-project filters
and accepts only status, priority, label, due, and sort. Project ownership is
established by the existing route binding before the query runs.

Add `ProjectTaskListController@index` and register `GET
/projects/{project}/tasks` as `projects.tasks.index`. The controller passes the
project, paginator, filter options, statuses, priorities, task-key service,
and a safe return context to the view.

## UI and accessibility

Use a table/list layout consistent with `My Work`, with a clear project header,
links back to overview and board, and a visible `New task` action. Keep filter
controls labelled and keyboard-operable without JavaScript. Status and
priority are readable text plus optional color, never color alone.

On desktop and tablet, use a scan-friendly table with hierarchy indentation
for direct subtasks. On mobile, stack row metadata and quick actions with no
horizontal clipping. Preserve visible focus, comfortable pointer targets,
theme/density settings, and reduced-motion behavior.

## Error and empty behavior

- A foreign project resolves as 404 through the existing owner-scoped binding.
- Invalid filter or sort values return validation errors without executing an
  unsafe query.
- Deleted tasks and subtasks are absent.
- An empty project shows `This project has no tracked work yet.` with a `New
  task` action.
- A filtered empty result shows `No tasks match these filters.` with a
  `Reset filters` link.
- Cancelled tasks are shown only when the explicit status filter selects them.
- Quick-action redirects never accept arbitrary URLs and preserve only the
  documented project-list query keys.

## Test contract

Add feature coverage for project ownership, top-level/subtask inclusion,
soft-delete exclusion, every filter and sort, due shortcuts, pagination,
metadata and detail links, empty states, accessible labels, quick-action
redirect context, and unchanged top-level-only project progress. Add browser
coverage when a browser harness is available for keyboard filtering, task
opening, quick actions, focus visibility, and mobile readability.

## Non-goals

- No recursive hierarchy beyond direct subtasks.
- No second drag-and-drop interaction; the board owns ordering.
- No saved filters, global search, or custom views.
- No progress recalculation changes or automatic parent/child status sync.

## Acceptance criteria

1. An owner can open a project task list and scan every visible task and
   direct subtask with complete readable metadata.
2. Filters, due shortcuts, sorting, and pagination are scoped to the project
   and owner.
3. Every row exposes a task-detail link and keyboard-operable quick actions.
4. Empty, deleted, cancelled, and foreign records follow the documented
   behavior.
5. Quick actions return safely to the same project-list context.
