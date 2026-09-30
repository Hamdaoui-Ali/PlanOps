## `AssignTask::handle` in app/Domain/Tasks/Actions/AssignTask.php (L18-L75)

**Purpose:** Authorizes and changes a task assignee, records the change, and dispatches a recipient notification
after the database transaction boundary.

**Inputs & Assumptions:**
- `$actor` (`User`): authenticated manager/legacy owner. Trust: trusted controller subject.
- `$task` (`Task`): route/domain task. Trust: semi-trusted; key is re-resolved through task visibility.
- `$assignee` (`User|null`): validated user or null. Trust: semi-trusted; membership/owner relationship is
  checked inside the transaction.
- Implicit: task project and membership rows are current under row locks (`AssignTask.php:L23-L36`).

**Outputs & Effects:**
- Returns the locked/refreshed task; may write `assignee_id`, append activity, and dispatch an
  `ASSIGNEE_CHANGED` notification after commit (`AssignTask.php:L23-L75`).

**Block-by-Block:**

```php
// L20-L21
Gate::forUser($actor)->authorize('assign', $task);
```
- **What:** Applies task assignment ability before writes.
- **Why here:** The action is callable outside the HTTP controller.
- **Assumes:** `TaskPolicy::assign` is registered and sees a current task/project relation.
- **Establishes:** ability-level authorization for this call.

```php
// L23-L37
$lockedTask = Task::query()->accessibleBy($actor)->whereKey(...)->lockForUpdate()->firstOrFail();
$membership = ... active membership ... lockForUpdate();
$project = $lockedTask->project()->first();
... owner identity ...
if ($assignee !== null && $membership === null && ! $assigneeIsProjectOwner) { throw ...; }
```
- **What:** Re-fetches the task, loads active assignee membership, accepts project owner identities, and
  rejects other users.
- **Why here:** Assignment target is a separate identity input and must be checked against the locked task's
  project.
- **Assumes:** owner/user IDs represent project creator/owner identity; `removed_at` marks active membership.
- **Establishes:** assignee is null, an active project member, or a project owner.
- **Depended on by:** assignee write at `L45` and notification outcome at `L60-L75`.

```php
// L39-L53
$oldAssigneeId = ...; if unchanged return; forceFill(...); TaskActivityRecorder::record(...);
```
- **What:** Writes the new assignee and appends actor-aware activity inside the transaction.
- **Why here:** Activity and task state share transaction success/failure.
- **Assumes:** recorder accepts the locked persisted task and the actor.
- **Establishes:** updated task assignment and activity state.

```php
// L59-L75
if ($assignee !== null && ... ) { $outcome = NotificationOutcome::assigneeChanged(...); ... }
```
- **What:** Dispatches notification directly or via `DB::afterCommit` depending on transaction level.
- **Why here:** Notification follows the task write and uses old/new assignee IDs.
- **Assumes:** `DeliverNotificationOutcome` rechecks current task/project/recipient state.
- **Establishes:** notification job dispatch intent, not recipient delivery.

**Cross-Function Dependencies:** `TaskPolicy::assign`, `Task::accessibleBy`, `ProjectMembership`,
`TaskActivityRecorder::record`, `DeliverNotificationOutcome`, `NotificationOutcome::assigneeChanged`.
- Callers: `TaskController::assign` (`TaskController.php:L35-L40`) and direct Action tests.
- Second assignee write path: `RemoveProjectMember::handle` (`RemoveProjectMember.php:L23-L49`).

**Open Questions:**
- The controller resolves the requested assignee with `User::findOrFail` after request `exists` validation
  (`TaskController.php:L35-L37`); current/deactivated-user state is not checked in this action's target
  membership/owner branch.
