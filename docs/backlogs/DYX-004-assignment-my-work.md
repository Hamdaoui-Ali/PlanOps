# DYX-004 — Assignment, actor history, and My Work

**Status:** Blocked until DYX-003 is accepted

**Priority:** P0

**Dependency:** [DYX-003](DYX-003-membership-invitations.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 12, 13, 26, 27, 28, 29, 30, 31, 32, 33, 34, 51, 52, 55, and 61–64.

## Goal

Make responsibility explicit with one nullable assignee per task, preserve who performed every mutation, and make My Work show only assigned tasks in accessible projects.

## Files

- `app/Domain/Tasks/Actions/AssignTask.php` or `ChangeTaskAssignee.php`
- task mutation actions, controllers, requests, and policies
- `app/Domain/Activity/Services/TaskActivityRecorder.php`
- `app/Domain/Activity/Models/TaskActivity.php`
- `app/Domain/Tasks/Queries/MyWorkQuery.php`
- task list, board, detail, and My Work views/components
- `routes/web.php`
- assignment, status, activity, and My Work tests

## Tasks

### Task DYX-004.1

Goal: Add the task assignment domain action.

Files: assignment action, task model/policy, request/controller, and assignment tests.

Action: Implement assign, reassign, and unassign with nullable `tasks.assignee_id`. Permit only Owner/Admin; require the target assignee to be an active member of the same project; allow unassignment explicitly; suppress a no-op activity event when the assignee does not change.

Why: assignment is a cross-record authorization rule and must not be implemented as a direct form-field update.

Verification: Test valid assignment, reassign, unassign, self-assignment, non-member target, removed target, cross-project target, unauthorized Member, archived project, and repeated same-value assignment.

Expected result: Every task has zero or one valid active project-member assignee.

### Task DYX-004.2

Goal: Make assignment safe under concurrent membership changes.

Files: assignment action, membership query/model, task policy, and PostgreSQL concurrency tests.

Action: Lock the task and relevant membership/project rows in a documented order; revalidate the target membership inside the transaction; handle a removal/assignment race deterministically; and unassign tasks according to the removal contract.

Why: a pre-request membership check can be stale before the write commits.

Verification: Run concurrent assignment/removal and assignment/ownership-transfer tests on PostgreSQL. Assert no task retains an invalid assignee and no transaction reports success for a removed target.

Expected result: Assignment cannot create a dangling or unauthorized responsibility under race conditions.

### Task DYX-004.3

Goal: Enforce assigned-member task status boundaries.

Files: `ChangeTaskStatus`, `TaskPolicy`, status request/action, and task authorization tests.

Action: Require an active assignment for Member status changes; allow only the documented non-`CANCELLED` transitions; keep Owner/Admin privileges explicit; reject archived-project writes and stale membership submissions; preserve existing transition timestamps and history.

Why: assignment must define a narrow execution boundary without granting Members project administration.

Verification: Test an assigned Member, unassigned Member, Member assigned to another project, Owner, Admin, removed Member, archived project, and `CANCELLED` attempts.

Expected result: Members can update the allowed status of their assigned task only; Owner/Admin rules remain intact.

### Task DYX-004.4

Goal: Make task activity actor-aware.

Files: `TaskActivityRecorder`, `TaskActivity`, every task mutation action, activity views, and activity tests.

Action: Require the authenticated actor for status, priority, due-date, assignment, content, reorder, completion, cancellation, restore, and delete changes. Record `ASSIGNEE_CHANGED` with old/new assignee identifiers and render actor names without replacing historical creator data.

Why: collaboration history must identify the person who acted, not only the task creator or current owner.

Verification: Each mutation test asserts `actor_user_id`, event type, old/new values, no-op suppression, and retained history after task deletion or project archive.

Expected result: Every task mutation has an accurate append-only actor record.

### Task DYX-004.5

Goal: Rewrite My Work around assignment and access scope.

Files: `MyWorkQuery`, dashboard/My Work routes and views, filters/sorting, and My Work tests.

Action: Query `tasks.assignee_id` for the authenticated viewer, join through active project membership, preserve existing filters/sorting/pagination, and exclude removed/deactivated viewers and inaccessible projects.

Why: My Work is a personal execution surface, not an owner or team-performance report.

Verification: Test assigned tasks across multiple accessible projects, unassigned tasks, tasks in inaccessible projects, removed membership, deactivated user, archived project, and existing filter/sort combinations.

Expected result: My Work shows exactly the viewer's assigned tasks in projects they can currently access.

### Task DYX-004.6

Goal: Expose assignment consistently in task experiences.

Files: task detail/list/board views, assignment selector, Member read-only display, route/controller tests, and accessibility checks.

Action: Show assignee state in detail, list, and board views. Give Owner/Admin an accessible member-only combobox; show Members the current assignee as read-only; preserve keyboard navigation, focus, and live status messaging.

Why: the UI must communicate responsibility without becoming the authorization layer.

Verification: Browser/axe/keyboard checks cover assignment, unassignment, validation errors, mobile layout, and forbidden direct requests when the browser suite is available.

Expected result: Users can understand task responsibility and authorized users can change it without an inaccessible control path.

## Acceptance criteria

- [ ] `tasks.assignee_id` is nullable and points only to an active member of the same project.
- [ ] Only Owner/Admin can assign, reassign, or unassign.
- [ ] Assignment and removal races are protected by transactional locking and revalidation.
- [ ] Members can change only allowed non-`CANCELLED` status values on tasks assigned to them.
- [ ] All task mutations identify the authenticated actor.
- [ ] `ASSIGNEE_CHANGED` records old/new assignee values and suppresses no-op events.
- [ ] My Work uses `assignee_id` plus active membership and preserves current filters/sorting.
- [ ] Detail, list, board, and My Work surfaces agree on assignee state and authorization.

## Verification commands

```text
php artisan test --filter=Assignment
php artisan test --filter=MyWork
php artisan test --filter=Activity
php artisan test --filter=TaskAuthorization
rg -n "TaskActivityRecorder|ASSIGNEE_CHANGED|assignee_id|MyWorkQuery|ChangeTaskStatus" app routes tests
```

Concurrency and row-lock checks must run against PostgreSQL.

## Expected result

Assignment, status changes, task history, and My Work share one membership-aware responsibility model and remain correct under stale requests and concurrent changes.

## Suggested commit boundaries

- `test: define assignment and actor-history invariants`
- `feat: add transactional task assignment`
- `feat: make task mutations actor-aware`
- `feat: rewrite My Work around assignees`
- `test: verify assignment races and member status boundaries`

## Next action

Lock assignment and actor-history tests, then implement `AssignTask`/`ChangeTaskAssignee` before changing My Work UI.
