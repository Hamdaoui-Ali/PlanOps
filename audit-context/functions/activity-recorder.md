# Task activity recorder

## `TaskActivityRecorder::record` in `app/Domain/Activity/Services/TaskActivityRecorder.php` (L18-L45)

### Purpose

Creates a normalized task activity row for a persisted task, with task/project/user identity, actor identity, event type, field values, and metadata (L18-L45).

### Inputs & Assumptions

- The caller supplies a task, activity type, optional field, old/new values, metadata, and optional actor (L18-L27).
- The task must already exist and have a key (L31-L33).
- `TASK_UPDATED` values for `title` and `description` are redacted from old/new value columns (L35-L36).

### Outputs & Effects

- Inserts and refreshes a `TaskActivity` row populated from the task and actor, with normalized/redacted value fields and metadata (L38-L45).
- The model’s `booted` hook prevents updates and deletes after creation (L36-L45; `app/Domain/Activity/Models/TaskActivity.php:L36-L45`).

### Block-by-Block

#### Persisted-task guard — L31-L33

- What: Rejects a task that is not persisted or has no key (L31-L33).
- Why: Ensures activity identity can be tied to a durable task row (L31-L33).
- Assumes: A non-zero task key is sufficient for the insert path to identify the task (L31-L33).
- Establishes: The record path only accepts persisted task instances (L31-L33).
- Depended on by: Task mutation actions and member-removal assignment cleanup (L31-L33; `app/Domain/Tasks/Actions/ChangeTaskStatus.php:L31-L46`, `app/Domain/Collaboration/Actions/RemoveProjectMember.php:L36-L46`).

#### Redaction decision — L35-L36

- What: Sets a redaction flag for `TASK_UPDATED` events whose field is `title` or `description` (L35-L36).
- Why: Prevents those field values from being sent to the value normalizers and stored in old/new columns (L38-L40).
- Assumes: Field names are compared case-insensitively after string conversion (L35-L36).
- Establishes: Redacted events store `null` for old/new values (L38-L40).
- Depended on by: Activity row creation (L38-L45).

#### Activity row construction — L38-L45

- What: Creates the activity row from task user/project/task IDs, actor ID fallback, type, field, normalized values, and normalized metadata, then refreshes it (L38-L45).
- Why: Centralizes activity shape for task mutation callers (L38-L45).
- Assumes: The task owner is the actor fallback when no explicit actor is provided (L40-L40).
- Establishes: A persisted activity record with task/project identity and normalized payload (L38-L45).
- Depended on by: Activity feed queries and analytics activity aggregation.

### Cross-Function Dependencies

- Called by task mutation actions and `RemoveProjectMember` (L38-L45; `app/Domain/Tasks/Actions/ChangeTaskStatus.php:L31-L46`, `app/Domain/Collaboration/Actions/RemoveProjectMember.php:L36-L46`).
- Its output is queried by `TaskActivityFeedQuery`, `AnalyticsQueryService`, `TeamAnalyticsQuery`, `DashboardQueryService`, and export paths (for example `app/Domain/Activity/Queries/TaskActivityFeedQuery.php:L14-L37`).

### Open Questions

- The record method redacts only the two named task fields; nothing found in this function establishes whether other metadata keys can contain equivalent descriptive values (L35-L45).

## `TaskActivityRecorder::normalizePayload` in `app/Domain/Activity/Services/TaskActivityRecorder.php` (L47-L54)

### Purpose

Normalizes a value and, for scalar normalized values with a field name, wraps it under that field name (L47-L54).

### Inputs & Assumptions

- The caller supplies an arbitrary value and optional field (L47-L48).
- `normalizeValue` recursively converts enums, dates, and arrays before field wrapping (L49-L54; L80-L101).

### Outputs & Effects

- Returns either the normalized value or a one-field associative array (L49-L54).

### Block-by-Block

#### Normalize and wrap — L49-L54

- What: Normalizes the input, then wraps non-array results as `[$field => $normalized]` when a field exists (L49-L54).
- Why: Gives scalar field changes a consistent payload shape while preserving arrays (L49-L54).
- Assumes: Array-typed values already carry their own structure (L51-L54).
- Establishes: The value shape used by `record` for old/new columns (L38-L45, L49-L54).
- Depended on by: `TaskActivityRecorder::record` (L38-L45).

### Cross-Function Dependencies

- Calls `normalizeValue` (L49-L49).

### Open Questions

- The method does not itself redact field names; redaction is decided by the caller before this method is invoked (L35-L40, L47-L54).

## `TaskActivityRecorder::normalizeMetadataValue` in `app/Domain/Activity/Services/TaskActivityRecorder.php` (L61-L78)

### Purpose

Recursively normalizes metadata while dropping keys whose lowercased names are `title` or `description` (L61-L78).

### Inputs & Assumptions

- Metadata may be scalar or nested array (L61-L64).
- Redaction applies to keys at every nested array level (L66-L73).

### Outputs & Effects

- Returns a normalized scalar or recursively rebuilt array without the two redacted key names (L62-L78).

### Block-by-Block

#### Scalar and nested handling — L62-L78

- What: Delegates non-arrays to `normalizeValue`; for arrays, skips redacted keys and recursively normalizes remaining items (L62-L78).
- Why: Gives metadata a JSON-compatible normalized structure with field-name filtering (L62-L78).
- Assumes: Metadata key names are meaningful even when nested and case varies (L66-L73).
- Establishes: Redaction behavior for metadata payloads (L66-L73).
- Depended on by: `normalizeMetadata` and `record` (L56-L59, L38-L45).

### Cross-Function Dependencies

- Calls `normalizeValue` for scalar leaves (L62-L64).

### Open Questions

- Redaction is key-name based; this function does not establish whether sensitive values stored under other keys are normalized or omitted (L66-L73).

## `TaskActivityRecorder::normalizeValue` in `app/Domain/Activity/Services/TaskActivityRecorder.php` (L80-L101)

### Purpose

Converts backed enums and date values to scalar representations and recursively normalizes arrays (L80-L101).

### Inputs & Assumptions

- Values may be backed enums, date/time objects, arrays, or scalar/null values (L80-L101).
- Date values are converted through Carbon to UTC ISO-8601 strings (L85-L87).

### Outputs & Effects

- Returns enum backing values, UTC ISO-8601 strings, unchanged non-array values, or recursively normalized arrays (L81-L101).

### Block-by-Block

#### Type conversion and recursion — L81-L101

- What: Checks `BackedEnum`, `DateTimeInterface`, non-array, then recursively rebuilds arrays (L81-L101).
- Why: Produces stable serialized values for activity persistence (L81-L101).
- Assumes: Backed enum values and UTC ISO-8601 dates are the desired persisted forms (L81-L87).
- Establishes: The scalar/array representation consumed by payload and metadata normalization (L49-L54, L62-L78).
- Depended on by: `record`, `normalizePayload`, and `normalizeMetadataValue`.

### Cross-Function Dependencies

- Uses `BackedEnum`, `DateTimeInterface`, and `Carbon` (L81-L87).

### Open Questions

- Non-array objects other than enums and dates pass through unchanged (L89-L90); nothing found in this method establishes which callers may supply such objects.
