## Request authorization and filter boundaries in app/Http/Requests (selected files)

This record groups related FormRequest methods because they are the shared request boundary for the route
functions in this dossier.

### `ActivityFiltersRequest::authorize`, `rules`, and `filters` (L11-L36)

**Purpose:** Requires an authenticated user, validates project/task/event/date filters, and returns only
non-null validated values (`ActivityFiltersRequest.php:L11-L36`).

**Inputs & Assumptions:**
- Raw query parameters are untrusted; `prepareForValidation` limits the copied keys to six fields and maps
  empty strings to null (`ActivityFiltersRequest.php:L16-L21`).
- IDs are integer-validated and event types enum-limited (`L23-L33`).

**Outputs & Effects:**
- `authorize` returns whether a user exists; `filters` returns validated non-empty values. No writes.

**Cross-Function Dependencies:** `ActivityController::index`, `TaskActivityFeedQuery::paginate`.

**Open Questions:**
- The request validates identifier shape but not project/task ownership; feed scope performs the record boundary.

---

### `DashboardPeriodRequest::authorize`, `rules`, and `selection` (L10-L33)

**Purpose:** Requires authentication and constrains dashboard/analytics period selection to known periods or a
custom date range (`DashboardPeriodRequest.php:L10-L33`).

**Inputs & Assumptions:**
- `period`, `from`, `until` are untrusted query values; only those keys are merged (`L15-L18`).
- `custom` requires date-formatted from/until and non-reversed validator order (`L21-L24`).

**Outputs & Effects:**
- `selection` returns period/default and optional dates (`L26-L33`). No writes.

**Cross-Function Dependencies:** `DashboardController`, `AnalyticsController`, project analytics controllers,
`UserPeriodResolver`.

**Open Questions:**
- `selection` validates shape but period object semantics (timezone/UTC conversion) are delegated to
  `UserPeriodResolver`.

---

### `SearchRequest::authorize`, `prepareForValidation`, `rules`, `term` (L9-L27)

**Purpose:** Requires authentication, trims a string search query, caps length, and exposes a string term
(`SearchRequest.php:L9-L27`).

**Outputs & Effects:** No writes; `term` returns validated `q` or empty string.

**Cross-Function Dependencies:** `SearchController::index`, `SearchQueryService::search`.

**Open Questions:**
- Search visibility is not a request concern; nothing here resolves IDs or scopes rows.

---

### `ExportRequest::authorize` and `rules` (L11-L18)

**Purpose:** Requires a viewer with at least one exportable project and constrains optional format to csv/json
(`ExportRequest.php:L11-L18`).

**Cross-Function Dependencies:** `ProjectPolicy::exportAny`, `ExportController`/`ExportQueryService`.

**Open Questions:**
- No project-specific export parameter is accepted, so per-row scope remains the query service responsibility.

---

### `MyWorkFiltersRequest` and `ProjectTaskListFiltersRequest` (L12-L54 / L12-L44)

**Purpose:** Require authentication, normalize allowed task filter fields, and expose validated arrays
(`MyWorkFiltersRequest.php:L12-L54`, `ProjectTaskListFiltersRequest.php:L12-L44`).

**Inputs & Assumptions:** Query filters are untrusted; enum/date/integer rules establish accepted shapes. The
project task list's `assignee` rule checks user existence only (`ProjectTaskListFiltersRequest.php:L29-L31`).

**Outputs & Effects:** `filters()` returns validated non-null values. No writes.

**Cross-Function Dependencies:** `MyWorkController`, `MyWorkQuery`, `ProjectTaskListController`,
`ProjectTaskListQuery`.

**Open Questions:**
- Neither request validates selected project/label/assignee membership; query scopes and project resolution are
  the downstream boundary.

---

### Task mutation request `authorize` methods (selected files)

`AssignTaskRequest::authorize` calls `can('assign', route('task'))` (`AssignTaskRequest.php:L9-L12`);
`ChangeTaskStatusRequest`, `ChangeTaskDueDateRequest`, `ChangeTaskPriorityRequest`, `UpdateTaskRequest`, and
`UpdateTaskDetailsRequest` require a `Task` route model and invoke the corresponding policy ability
(`ChangeTaskStatusRequest.php:L12-L16`, `ChangeTaskDueDateRequest.php:L10-L16`,
`ChangeTaskPriorityRequest.php:L12-L18`, `UpdateTaskRequest.php:L10-L15`,
`UpdateTaskDetailsRequest.php:L13-L19`). `StoreTaskRequest` requires a `Project` route model and `create`
ability for `[Task::class, project]` (`StoreTaskRequest.php:L14-L19`). `ReorderTasksRequest` invokes project
`reorder` (`ReorderTasksRequest.php:L11-L14`).

**Assumes:** route binding has produced the expected model type before FormRequest authorization. Nothing in
these request methods independently calls `accessibleBy`; Actions and query objects re-fetch scoped records.

**Open Questions:**
- Exact framework ordering between implicit model binding and FormRequest authorization is not established in
the repository files.
