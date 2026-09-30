## `ProjectPolicy::role` in app/Policies/ProjectPolicy.php (L87-L100)

**Purpose:** Converts a user/project pair into the policy role used by project view, content, analytics,
Team Work, export, member, and ordering abilities.

**Inputs & Assumptions:**
- `$user` (`User`): authenticated policy subject. Trust: trusted framework subject.
- `$project` (`Project`): route/query model. Trust: semi-trusted; route binding or caller must establish its
  identity.
- Implicit: `owner_id`/`user_id` identify owner, and a membership is active when `removed_at` is null
  (`ProjectPolicy.php:L89-L99`).

**Outputs & Effects:**
- Returns `OWNER` for either owner column, an active membership role, or `null` (`ProjectPolicy.php:L89-L99`).
  Reads membership state; no writes.

**Block-by-Block:**

```php
// L89-L99
if ((string) $project->owner_id === (string) $user->getKey()
    || (string) $project->user_id === (string) $user->getKey()) {
    return ProjectRole::OWNER;
}
$membership = $project->memberships()->where('user_id', $user->getKey())->whereNull('removed_at')->first();
if ($membership) { return $membership->role; }
return null;
```
- **What:** Checks owner identity before querying active membership.
- **Why here:** Legacy creators remain owners even when an owner membership row is absent.
- **Assumes:** `removed_at` is the sole active-membership marker; membership role is a valid `ProjectRole`.
- **Establishes:** The role consumed by every policy method in this class.
- **Depended on by:** `view`, `canManageContent`, analytics, Team Work, export, member, role, and reorder
  abilities (`ProjectPolicy.php:L16-L79`).

**Cross-Function Dependencies:**
- Callee `memberships` relation (`Project.php:L72-L75`).
- Policy registration is in `AppServiceProvider::boot` (`app/Providers/AppServiceProvider.php:L27-L32`).
- The project model's `accessibleBy` scope uses the same owner/user/removed-membership fields
  (`Project.php:L92-L104`).

**Open Questions:**
- The policy does not inspect `User::deactivated_at`; nothing found in `ProjectPolicy.php:L87-L100` makes
  that state affect role resolution.

---

## `ProjectPolicy::canManageContent` in app/Policies/ProjectPolicy.php (L81-L85)

**Purpose:** Shared policy predicate for update, status change, archive, export, and reorder content actions.

**Inputs & Assumptions:**
- `$user` and `$project`: trusted policy subject plus route/query model.
- Implicit: `role($user, $project)` is current and `archived_at === null` means content is writable
  (`ProjectPolicy.php:L81-L85`).

**Outputs & Effects:**
- Returns true only for OWNER/ADMIN on an unarchived project (`ProjectPolicy.php:L81-L85`). No writes.

**Cross-Function Dependencies:**
- Callee `role` (`ProjectPolicy.php:L87-L100`): provides owner/active-membership role.
- Callers: `update`, `changeStatus`, `archive`, and `reorder` (`ProjectPolicy.php:L26-L39`, `L76-L85`).
- Analytics and Team Work intentionally call `role` directly and do not use this archived-content predicate
  (`ProjectPolicy.php:L51-L59`).

**Open Questions:**
- Nothing in the method checks project status enum, only `archived_at`; status interpretation belongs to
  callers/actions.

---

## `ProjectPolicy::exportAny` in app/Policies/ProjectPolicy.php (L61-L64)

**Purpose:** Decides whether the current user can enter any of the complete export endpoints.

**Inputs & Assumptions:**
- `$user` (`User`): authenticated policy subject.
- Implicit: at least one project satisfying `exportableBy` is enough to authorize the request
  (`ProjectPolicy.php:L61-L64`).

**Outputs & Effects:**
- Executes an existence query and returns boolean; no writes.

**Cross-Function Dependencies:**
- Callee `Project::scopeExportableBy` → `detailedReportsVisibleTo` (`Project.php:L106-L126`).
- Caller `ExportRequest::authorize` (`app/Http/Requests/ExportRequest.php:L11-L14`).
- The authorized stream later applies export scope again per record in `ExportQueryService`
  (`ExportQueryService.php:L14-L35`).

**Open Questions:**
- The policy is a global existence check rather than an endpoint-specific project argument; the streams are
  the subsequent data boundary.

---

## `TaskPolicy::changeStatus` in app/Policies/TaskPolicy.php (L52-L64)

**Purpose:** Determines whether a user may transition a task's status.

**Inputs & Assumptions:**
- `$user` (`User`) and `$task` (`Task`): policy subject and task. Trust: framework/caller supplied.
- Implicit: task's `project`, project archive state, owner/active membership role, and `assignee_id`
  (`TaskPolicy.php:L54-L64`).

**Outputs & Effects:**
- Returns false for missing/archived project, true for legacy task owner, or true for OWNER/ADMIN and an
  assigned MEMBER (`TaskPolicy.php:L54-L64`). No writes.

**Block-by-Block:**

```php
// L54-L64
if (! $task->project || $task->project->archived_at !== null) { return false; }
if ($this->legacyTaskOwner($user, $task)) { return true; }
$role = $this->role($user, $task->project);
return in_array($role, [ProjectRole::OWNER, ProjectRole::ADMIN], true)
    || ($role === ProjectRole::MEMBER && (int) $task->assignee_id === (int) $user->getKey());
```
- **What:** Combines project state, legacy ownership, role, and assignee identity.
- **Why here:** Status changes are the one task mutation explicitly available to an assigned member.
- **Assumes:** `role` and `legacyTaskOwner` use the same membership/owner interpretation as project scope.
- **Establishes:** Ability-level authorization before the status Action re-fetches the task.
- **Depended on by:** `ChangeTaskStatusRequest`, `ChangeTaskStatus::handle`, and board/task status routes.

**Cross-Function Dependencies:**
- Callees `legacyTaskOwner`, `role` (internal, `TaskPolicy.php:L81-L105`).
- Registration is in `AppServiceProvider::boot` (`AppServiceProvider.php:L27-L32`).

**Open Questions:**
- The policy reads the route/model's loaded project and assignee before the Action's locked re-fetch; callers
  rely on both checks agreeing for the same task ID.

---

## `TaskPolicy::canManageTask` in app/Policies/TaskPolicy.php (L76-L85)

**Purpose:** Shared ability predicate for task update, priority, due date, delete, restore, and assignment.

**Inputs & Assumptions:**
- `$user`, `$task`: policy subject and task model.
- Implicit: task has a project, the project is manageable through role/archive state, or the task meets the
  legacy-owner/no-membership rule (`TaskPolicy.php:L76-L85`).

**Outputs & Effects:**
- Returns the combination of project presence and either `canManage` or `legacyTaskOwner` (`L76-L79`). No
  writes.

**Cross-Function Dependencies:**
- Callees `canManage` (`TaskPolicy.php:L87-L91`) and `legacyTaskOwner` (`L81-L85`), which in turn call
  `role` (`L93-L105`).
- Callers: `update`, `changePriority`, `changeDueDate`, `delete`, `restore`, and `assign`
  (`TaskPolicy.php:L22-L44`, `L66-L69`).

**Open Questions:**
- Nothing in this method independently checks `Task::accessibleBy`; callers and the downstream Actions carry
  the record-scope precondition.
