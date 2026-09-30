## `TeamWorkQuery::for` in app/Domain/Collaboration/Queries/TeamWorkQuery.php (L15-L59)

**Purpose:** Builds manager-visible Team Work data for one project: active members, task workload, and current
week completion counts.

**Inputs & Assumptions:**
- `$viewer` (`User`): authenticated viewer. Trust: trusted controller subject.
- `$project` (`Project`): route model, re-resolved through `Project::accessibleBy` (`TeamWorkQuery.php:L18-L19`).
- `$now` (`CarbonImmutable|null`): injectable time. Trust: internal/test input.
- Implicit: Team Work callers are already authorized by `viewTeamWork`; query adds project/task read scope
  (`ProjectTeamWorkController.php:L14-L22`, `TeamWorkQuery.php:L18-L31`).

**Outputs & Effects:**
- Reads project, top-level accessible tasks, active memberships/users, and period preferences; returns a
  `TeamWorkSnapshot` (`TeamWorkQuery.php:L18-L59`). No writes.

**Block-by-Block:**

```php
// L18-L31
$project = Project::query()->accessibleBy($viewer)->whereKey(...)->firstOrFail();
$period = ...; $tasks = Task::query()->accessibleBy($viewer)->where('project_id', ...)->whereNull(...)->get(...);
$memberships = $project->activeMemberships()->with(...)->get();
```
- **What:** Establishes project, task, time-zone, and active-member inputs.
- **Why here:** All member aggregates derive from the same scoped collections.
- **Assumes:** active membership relation and task assignment IDs use the same project context.
- **Establishes:** snapshot input state.

```php
// L33-L59
$members = $memberships->map(...); return new TeamWorkSnapshot(...);
```
- **What:** Aggregates active/blocked/overdue/completed counts per active member and unassigned count.
- **Why here:** Presentation snapshot is built only after scope queries complete.
- **Assumes:** assignment IDs correspond to membership user IDs; terminal statuses exclude done/cancelled from
  unassigned count.
- **Establishes:** team workload snapshot.

**Cross-Function Dependencies:**
- Callees `Project::accessibleBy`, `Task::accessibleBy`, `Project::activeMemberships`, and
  `UserPeriodResolver::week` (`UserPeriodResolver.php:L27-L36`).
- Caller `ProjectTeamWorkController::show`.
- Tests cover owner/admin route access, removed/foreign project resolution, and workload values
  (`tests/Feature/Collaboration/TeamWorkHttpTest.php:L15-L105`, `tests/Unit/Domain/Collaboration/TeamWorkQueryTest.php`).

**Open Questions:**
- The query's project scope is ordinary `accessibleBy`, while the controller's ability is manager-only; the
  two conditions are expected to compose, not substitute for each other.

---

## `NotificationCenterQuery::for` in app/Domain/Notifications/Queries/NotificationCenterQuery.php (L11-L14)

**Purpose:** Creates the notification-center query for one recipient.

**Inputs & Assumptions:**
- `$recipient` (`User`): authenticated user. Trust: trusted controller subject.
- Implicit: `recipient_id` is the notification ownership field (`PlanOpsNotification.php:L36-L39`).

**Outputs & Effects:**
- Returns a builder filtered by recipient and ordered newest-first (`NotificationCenterQuery.php:L11-L14`).
  No writes.

**Cross-Function Dependencies:**
- Callee `PlanOpsNotification::scopeForRecipient` (`PlanOpsNotification.php:L36-L39`).
- Caller `NotificationController::index` (`NotificationController.php:L28-L47`).

**Open Questions:**
- Nothing in this query filters notification event type or project; those constraints are applied by callers
  when invitation actions are selected.
