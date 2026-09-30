## `Project::scopeAccessibleBy` in app/Domain/Projects/Models/Project.php (L92-L104)

**Purpose:** Adds the primary project read predicate used by route bindings, project lists, collaboration
queries, and task visibility.

**Inputs & Assumptions:**
- `$query` (Eloquent builder): builder for `Project`. Trust: trusted internal query construction.
- `$viewer` (`User|int`): viewer identity. Trust: semi-trusted; callers mostly pass the authenticated user,
  while query objects also accept an integer ID (`Project.php:L92-L95`).
- Implicit: `projects.owner_id`, `projects.user_id`, and `project_memberships.removed_at`. Their meaning is
  established by the predicate itself (`Project.php:L97-L103`). No `deactivated_at` predicate is present;
  nothing found in this function establishes one (`Project.php:L92-L104`).

**Outputs & Effects:**
- Returns the same builder with an OR group: owner identity, legacy user identity, or active membership
  (`Project.php:L97-L103`). No writes.

**Block-by-Block:**

```php
// L94-L95
$viewerId = $viewer instanceof User ? $viewer->getKey() : $viewer;
$table = $query->getModel()->getTable();
```
- **What:** Normalizes the viewer to an ID and obtains the current table name.
- **Why here:** The ID is reused for qualified owner/user columns and membership lookup.
- **Assumes:** An integer caller identifies a real user; no existence lookup occurs here.
- **Establishes:** `$viewerId` is the value used for all three visibility branches.
- **Depended on by:** The predicate at `L97-L103`.

```php
// L97-L103
return $query->where(function (Builder $projects) use ($viewerId, $table): void {
    $projects->where($table.'.owner_id', $viewerId)
        ->orWhere($table.'.user_id', $viewerId)
        ->orWhereHas('memberships', fn (Builder $memberships): Builder => $memberships
            ->where('user_id', $viewerId)
            ->whereNull('removed_at'));
});
```
- **What:** Admits project owners, legacy creators, and active members.
- **Why here:** The grouped OR keeps the three identity paths attached to the same project row.
- **Assumes:** `removed_at IS NULL` is the active-membership invariant; callers do not add a broader relation
  that changes the grouped predicate.
- **Establishes:** Any returned project is readable under the application's primary collaboration scope.
- **Depended on by:** Route bindings, `Task::accessibleBy`, project/task/query objects, and Actions that
  re-fetch project/task records.

**Cross-Function Dependencies:**
- Callee `memberships` relation (internal model relation, `Project.php:L72-L75`): supplies project-scoped
  membership rows.
- Callers: `routes/web.php:L44-L46`; `ProjectIndexQuery`, `ProjectOverviewQuery`, `ProjectBoardQuery`,
  `ProjectTaskListQuery`, `TeamWorkQuery`, `ProjectActivityFeedQuery`, and mutation Actions recorded in
  sibling files.
- Invariant coupling: `ProjectPolicy::role` independently interprets the same owner/user and
  `removed_at` fields (`ProjectPolicy.php:L87-L99`).

**Open Questions:**
- Whether a deactivated viewer should be excluded from this predicate is not checked here; nothing found in
  `Project.php:L92-L104`.

---

## `Project::scopeDetailedReportsVisibleTo` in app/Domain/Projects/Models/Project.php (L111-L126)

**Purpose:** Defines the stricter project set used by global analytics and complete exports.

**Inputs & Assumptions:**
- `$viewer` (`User|int`): report viewer. Trust: semi-trusted; normalized to an ID at `L113`.
- Implicit: membership role values and `removed_at`, plus legacy projects having no membership rows
  (`Project.php:L116-L125`).

**Outputs & Effects:**
- Returns a builder limited to active OWNER/ADMIN members or legacy owner/user projects with no memberships
  (`Project.php:L116-L125`). No writes.

**Block-by-Block:**

```php
// L116-L125
return $query->where(function (Builder $projects) use ($viewerId, $table): void {
    $projects->whereHas('memberships', fn (Builder $memberships): Builder => $memberships
        ->where('user_id', $viewerId)
        ->whereIn('role', [ProjectRole::OWNER->value, ProjectRole::ADMIN->value])
        ->whereNull('removed_at'))
        ->orWhere(fn (Builder $legacy): Builder => $legacy->whereDoesntHave('memberships')
            ->where(fn (Builder $owners): Builder => $owners
                ->where($table.'.owner_id', $viewerId)
                ->orWhere($table.'.user_id', $viewerId)));
});
```
- **What:** Separates manager-level report visibility from ordinary membership visibility.
- **Why here:** The query is shared by analytics and exports, where the caller expects a complete project
  report rather than ordinary project viewing.
- **Assumes:** Role enum values stored in memberships match `ProjectRole::OWNER` and `ADMIN`; a project with
  any membership row is not treated as legacy.
- **Establishes:** A project row qualifies for detailed report/export queries.
- **Depended on by:** `scopeExportableBy`, `ProjectPolicy::exportAny`, `AnalyticsQueryService`,
  `TeamAnalyticsQuery`, and `ExportQueryService`.

**Cross-Function Dependencies:**
- Callee `memberships` relation (internal, `Project.php:L72-L75`).
- Callers: `Project::scopeExportableBy` at `L106-L109`; `ProjectPolicy::exportAny` at `L61-L64`;
  analytics/export records.
- Shared state: membership role/removal columns and project owner/user columns.

**Open Questions:**
- Nothing in this scope checks that the membership's related user is active; only `removed_at` is part of the
  predicate (`Project.php:L117-L120`).

---

## `Project::scopeExportableBy` in app/Domain/Projects/Models/Project.php (L106-L109)

**Purpose:** Names the export policy scope and delegates its predicate to the detailed-report scope.

**Inputs & Assumptions:**
- `$viewer` (`User|int`): report/export viewer. Trust: semi-trusted.

**Outputs & Effects:**
- Returns `detailedReportsVisibleTo($viewer)` with no additional conditions
  (`Project.php:L106-L109`). No writes.

**Block-by-Block:**

```php
// L106-L109
public function scopeExportableBy(Builder $query, User|int $viewer): Builder
{
    return $query->detailedReportsVisibleTo($viewer);
}
```
- **What:** Makes export scope an alias of detailed report scope.
- **Why here:** Policy and stream code can use an intent-specific name while sharing one predicate.
- **Assumes:** Export and detailed-report visibility are the same contract.
- **Establishes:** No independent export rule beyond `scopeDetailedReportsVisibleTo`.
- **Depended on by:** `ProjectPolicy::exportAny` and all three export query methods.

**Cross-Function Dependencies:**
- Callee `scopeDetailedReportsVisibleTo` (internal, `Project.php:L111-L126`): all behavior comes from that
  method.

**Open Questions:**
- None in the visible implementation; callers that need a different export subset would not get it from this
  scope.

---

## `Project::scopeOwnedBy` in app/Domain/Projects/Models/Project.php (L128-L133)

**Purpose:** Provides the legacy owner-only predicate retained for owner-specific operations and tests.

**Inputs & Assumptions:**
- `$owner` (`User|int`): owner identity. Trust: semi-trusted.
- Implicit: `projects.user_id` is the column this compatibility scope means by "owned"
  (`Project.php:L128-L133`). It does not inspect `owner_id`, memberships, or `removed_at`; nothing found
  establishes those conditions here.

**Outputs & Effects:**
- Adds a qualified `user_id = ownerId` predicate (`Project.php:L128-L133`). No writes.

**Cross-Function Dependencies:**
- Callers visible in scope include ownership tests (`tests/Feature/Authorization/OwnershipScopeTest.php:L20-L39`).
- It is not the scope used by the main collaboration route binding or the global export/search/activity
  surfaces, which use `accessibleBy` or `exportableBy` in the files recorded here.

**Open Questions:**
- Whether any out-of-scope code still uses this predicate for a collaboration-facing response remains an
  inventory question; the scoped `rg` search found the implementation and tests, but no main route caller.
