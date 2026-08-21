# PlanOps screen contract

## Route map

| Route | Screen / responsibility |
| --- | --- |
| `GET /dashboard` | Dashboard overview and period progress. |
| `GET /my-work` | Cross-project execution view. |
| `GET /projects`, `POST /projects` | Projects index and creation. |
| `GET/PATCH /projects/{project}` | Project Overview and editing. |
| `POST /projects/{project}/status` | Explicit project lifecycle status change. |
| `POST /projects/{project}/archive` | Archive project without deleting its history. |
| `POST /projects/{project}/restore` | Restore an archived project. |
| `GET /projects/{project}/board` | Project Board. |
| `GET /projects/{project}/tasks` | Project Tasks list. |
| `GET /projects/{project}/analytics` | Project analytics. |
| `GET /projects/{project}/activity` | Project-scoped activity. |
| `POST /projects/{project}/tasks` | Quick task creation. |
| `GET/PATCH/DELETE /tasks/{task}`, `POST /tasks/{task}/restore` | Task Detail and lifecycle editing. |
| `POST /tasks/{task}/status`, `/priority`, `/subtasks`, `/labels`; `DELETE /tasks/{task}/labels/{label}` | Explicit task actions. |
| `POST /projects/{project}/board/reorder` | Transactional board ordering. |
| `GET /activity` | Global Activity. |
| `GET /analytics` | Global Analytics. |
| `GET /search` | Global Search. |
| `GET /settings`, `PATCH /settings/preferences` | Personal Settings. |

All routes authorize ownership and scope every result to the authenticated user; resources owned by another user may resolve as 404.

## Required screens

### Dashboard — `/dashboard`

Show the visible period selector `Today`, `Week`, `Month`, `Year`, and `Custom`, with timezone-aware boundaries. Keep current-state KPIs semantically stable while period-event metrics and chart buckets change. Required sections are Active Projects, In Progress, In Review, Blocked, Completed in Period, optional Overdue, Currently Working On, In Review, Blocked, Due Today / Overdue, period activity, recent timeline, trends, project contribution, and attention indicators. Empty states teach: create a first project; no task state changes in this period; or nothing was marked Done in this period.

### My Work — `/my-work`

Cross-project table/list defaults to In Progress, In Review, Blocked, and Not Started; Backlog and Done are filterable. Each row shows task key, project, title, and status. Filters: project, status, priority, label, due state, created date, updated date. Due shortcuts: overdue, due today, due this week, no due date. Sorts: recently updated (default), recently created, priority, due date, task key, project. Include a clear empty result state with a reset-filter path.

### Projects — `/projects`

Show project cards or rows with name, key, manual status, derived progress, done/total, active status counts, and quick creation. Filter by status, archived/active, and target date; sort by recently updated, name, progress, target date, or creation date. Empty state: `Create your first project to start organizing work.`

### Project Overview — `/projects/{project}`

Required sections: project header (name, key, status, target date, progress); Current Work (In Progress, In Review, Blocked); Progress Summary (Done/total, Not Started, status distribution); Recent Activity; Upcoming / Overdue. If it has no tasks: `This project has no tracked work yet. Add the first task.` Progress with no eligible tasks reads `0%` and `No active scope`.

### Board — `/projects/{project}/board`

Default columns are Backlog, Not Started, In Progress, In Review, Blocked, and Done; Cancelled is hidden by default but filterable. Cards represent top-level tasks and contain key, title, priority, optional due date, label chips, and subtask progress. Dragging across columns may change status and within a column changes position, but there is **No drag-only behavior**: every card exposes a keyboard-operable status selector or `Move to…` action. Deletion confirms; normal status changes do not.

### Tasks — `/projects/{project}/tasks`

Provide a scan-friendly list with Key, Title, Status, Priority, Due, Labels, Subtasks, and Updated. Users can filter, sort, open details, create a task, and change status or priority quickly. Quick creation requires only Project and Title; defaults are `NOT_STARTED` and `MEDIUM`.

### Task Detail — `/tasks/{task}`

May open in a contextual desktop drawer with a dedicated URL option. Required content: key and title, status, priority, project, due date, labels, description, subtasks, and readable chronological Activity. Keep the key easy to copy but visually secondary to title. Subtasks show a compact completion summary and can open their own detail screen. Never render raw TaskActivity JSON.

### Activity — `/activity`

Global chronological feed answers what changed across all projects. Group by date and include task key plus a readable change. Filters: Project, Event type, Date range, Task. The project-scoped route uses the same contract. Empty state explains that no activity matches the selected filters.

### Analytics — `/analytics` and `/projects/{project}/analytics`

Use the Dashboard date-range contract. Global sections are Overview, Throughput, Workflow, Projects, and Activity. Show Created, Completed, Started, Moved to Review, Blocked, Reopened, and time metrics only when data is sufficient. Required no-data copy: `Not enough completed tasks in this period to calculate cycle time.` Charts have labels, accessible tooltips, and a table/text alternative; Activity is explicitly tracked work activity, not productivity. Project analytics include progress, top-level total, status counts, selected-period created/completed, and eligible lead/cycle metrics.

### Search — `/search`

Global search supports task key, title, description, project name, and labels. Task results show key, title, project, status; project results show name, key, status, progress. Results are capped, user-scoped, and keyboard navigable. Show an explicit empty state when no results match.

### Settings — `/settings`

Manage IANA timezone, week start (Monday or Sunday), theme (System, Light, Dark), and density (Comfortable default or Compact). Clarify that timezone drives period boundaries and `Today` calculations.

## Interaction, accessibility, and responsive acceptance contract

Every core action is operable without a mouse: sidebar navigation, opening tasks, filters, changing status/priority, creating tasks, submitting forms, closing dialogs, and Search. Focus is visible in light and dark themes; status is text plus color, never color alone; reduced motion is respected. Desktop keeps sidebar, multi-column board, drawer, and full charts; tablet collapses navigation and permits horizontal board scrolling; mobile becomes list-first while preserving the status-control alternative and numeric chart summaries. P2 features are out of scope for all screens.
