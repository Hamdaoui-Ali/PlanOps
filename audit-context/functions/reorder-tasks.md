## `ReorderTasks::handle` in app/Domain/Tasks/Actions/ReorderTasks.php (L17-L57)

**Purpose:** Validates a submitted board order, verifies project/task/status membership, locks the selected
tasks, and writes sequential positions.

**Inputs & Assumptions:**
- `$owner` (`User`): authenticated manager. Trust: trusted controller subject.
- `$project` (`Project`): route project. Trust: semi-trusted; existence is rechecked through project scope.
- `$status` (`TaskStatus`): validated enum.
- `$orderedTaskIds` (array<int,int|string>): request array validated for integer/distinct shape by
  `ReorderTasksRequest`, then revalidated here (`ReorderTasks.php:L17-L31`).

**Outputs & Effects:**
- Transactionally writes `position` on each selected task, or throws a validation exception; no return value
  (`ReorderTasks.php:L17-L52`).

**Block-by-Block:**

```php
// L19-L31
$ids = array_map('intval', ...); if (...) { $this->invalidOrder(...); }
if (! Project::query()->accessibleBy($owner)->whereKey(...)->exists()) { ... }
```
- **What:** Normalizes IDs, checks uniqueness/integer shape, and verifies project visibility.
- **Why here:** Rejects malformed input before locking task rows.
- **Assumes:** integer conversion preserves intended IDs only after the explicit count/filter checks.
- **Establishes:** normalized unique ID list and accessible project.

```php
// L34-L45
$tasks = Task::query()->accessibleBy($owner)->where('project_id', ...)->whereNull(...)->where('status', ...)->whereIn('id',$ids)->lockForUpdate()->get();
if ($tasks->count() !== count($ids)) { ... }
```
- **What:** Loads exactly the requested accessible top-level tasks in the requested status column.
- **Why here:** The count comparison binds every submitted ID to the same project/status set.
- **Assumes:** `count()` after `whereIn` identifies missing, foreign, inaccessible, or mismatched rows equally.
- **Establishes:** complete locked task set for the write loop.

```php
// L47-L52
foreach ($ids as $position => $id) { $tasks->get($id)->forceFill(['position' => $position])->save(); }
```
- **What:** Writes positions in request order.
- **Why here:** Runs only after full-set validation and row locks.
- **Assumes:** collection keys are task IDs and all IDs passed the count check.
- **Establishes:** positions for the requested status column.

**Cross-Function Dependencies:** `Project::accessibleBy`, `Task::accessibleBy`, `ReorderTasksRequest`,
`ProjectBoardController::reorder`.

**Open Questions:**
- The action authorizes through the FormRequest/policy path rather than calling `Gate` itself; direct callers
  must supply a caller already authorized as manager (`ReorderTasks.php:L17-L18`).
