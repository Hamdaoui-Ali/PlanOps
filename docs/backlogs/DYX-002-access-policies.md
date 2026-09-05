# DYX-002 — Access scopes and policies

**Status:** Blocked until DYX-001 is accepted

**Priority:** P0

**Dependency:** [DYX-001](DYX-001-schema-migration.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 8, 9, 23, 24, 25, 26, 40, 41, 42, 43, 51, 52, 55, and 56.

## Goal

Replace owner-only access with one membership-aware authorization layer for every project/task read, mutation, route binding, search result, activity feed, analytics query, dashboard count, and export.

## Files

- `app/Domain/Projects/Models/Project.php`
- `app/Domain/Tasks/Models/Task.php`
- `app/Domain/Labels/Models/Label.php`
- `app/Policies/ProjectPolicy.php`
- `app/Policies/TaskPolicy.php`
- `app/Policies/LabelPolicy.php`
- `routes/web.php`
- all project/task/label/search/activity/analytics/dashboard query classes
- `app/Http/Requests/ReorderTasksRequest.php`
- `app/Domain/Tasks/Actions/ReorderTasks.php`
- `app/Http/Controllers/ExportController.php`
- authorization and route-security feature tests

## Tasks

### Task DYX-002.1

Goal: Create the canonical query access scopes.

Files: `Project.php`, `Task.php`, label/search/activity/analytics/dashboard query classes, and query tests.

Action: Implement `Project::query()->accessibleBy($viewer)` and the equivalent task/label access path. Define active membership as `removed_at IS NULL`; include the viewer's projects only when the required project relationship is satisfied.

Why: every consumer needs the same scope instead of reimplementing membership joins.

Verification: Query tests cover Owner, Admin, Member, removed member, deactivated user, archived project, cross-project task, and a viewer with no membership.

Expected result: A single, reusable access path returns only records the viewer can see.

### Task DYX-002.2

Goal: Encode the permission matrix in explicit policies.

Files: `ProjectPolicy.php`, `TaskPolicy.php`, `LabelPolicy.php`, policy tests, and authorization registration.

Action: Implement explicit abilities for `view`, `create`, `update`, `delete`, `restore`, `changeStatus`, `changePriority`, `changeDueDate`, `assign`, `reorder`, `viewAnalytics`, and `export`. Keep Owner/Admin/Member rules from the authority document: Members can update only assigned task content and status within the non-`CANCELLED` rule; only Owner/Admin can assign; only Owner can transfer ownership or change roles.

Why: implicit controller checks are easy to miss and impossible to audit as the surface grows.

Verification: Policy tests assert allow/deny results for every role and every named ability, including archived and removed membership states.

Expected result: The authorization decision is explicit, testable, and independent of UI visibility.

### Task DYX-002.3

Goal: Close route-model binding and nested-resource gaps.

Files: `routes/web.php`, route bindings, controllers, form requests, and route-security tests.

Action: Replace `ownedBy()` assumptions in route bindings with membership-aware resolution. Ensure nested task routes verify that the task belongs to the resolved project before policy authorization. Keep invitation token preview privacy separate from authenticated project resolution.

Why: direct URLs and mismatched nested identifiers are a common cross-project leakage path.

Verification: Request tests cover a valid task/project pair, a task from another project, nonexistent records, removed membership, archived project, and direct access to every project-scoped route.

Expected result: A viewer cannot use a valid identifier from another project to bypass project membership.

### Task DYX-002.4

Goal: Audit every read and write surface for scope and ability checks.

Files: all query classes and controllers listed in the source specification, including `ReorderTasksRequest.php`, `ReorderTasks.php`, `ExportController.php`, `TaskKeyQuery`, search, activity, analytics, and dashboard surfaces.

Action: For each surface, document the required query scope and policy ability. Add missing request authorization, controller `authorize()` calls, nested checks, and mutation-time membership revalidation. Remove security-critical direct `ownedBy()` callers.

Why: the highest-risk bug is a forgotten secondary surface that still trusts legacy ownership.

Verification: Use repository-wide searches plus request tests. Specifically prove that reorder, export, search, activity, analytics, dashboard, and task-key lookup cannot cross project boundaries.

Expected result: No security-critical surface relies on the legacy single-owner assumption.

### Task DYX-002.5

Goal: Preserve archived-project and account-lifecycle behavior.

Files: project/task policies, mutation actions/controllers, and lifecycle tests.

Action: Allow the documented read-only views for archived projects, reject all writes with a stable validation/authorization response, and reject mutation by removed or deactivated users even when a stale form is submitted.

Why: access must be enforced at the server boundary, not only by disabling buttons.

Verification: Request tests submit stale forms and direct mutation requests for archived projects, removed memberships, and deactivated accounts.

Expected result: Archived and removed states are safe under normal navigation and adversarial direct requests.

## Role acceptance matrix

| Ability | Owner | Admin | Member |
| --- | --- | --- | --- |
| View project/task data | Yes | Yes | Yes, active membership |
| Create project | Yes | Yes, subject to product route rules | No |
| Invite Member | Yes | Yes, but not Admin | No |
| Change Member/Admin roles | Yes | No | No |
| Transfer ownership | Yes | No | No |
| Assign/reassign/unassign task | Yes | Yes | No |
| Update assigned task content | Yes | Yes | Yes, assigned task only |
| Change assigned task to `CANCELLED` | Yes | Yes | No |
| Change other task status | Yes | Yes | Yes, assigned task only |
| Manage project labels | Yes | Yes | No |
| View analytics/export | Yes | Yes | No |

## Acceptance criteria

- [ ] `accessibleBy($viewer)` is the canonical read scope for project-scoped records.
- [ ] Every policy ability is explicit and tested for Owner, Admin, Member, removed, deactivated, and archived states.
- [ ] Nested route bindings reject project/task mismatches before returning or mutating data.
- [ ] Search, activity, analytics, dashboard, export, reorder, and task-key surfaces use the required scope.
- [ ] No security-critical `ownedBy()` assumption remains in a collaboration path.
- [ ] Server-side authorization remains correct when UI controls are bypassed.

## Verification commands

```text
php artisan route:list --except-vendor
php artisan test --filter=Authorization
php artisan test --filter=RouteSecurity
rg -n "ownedBy\(|accessibleBy\(|viewAnalytics|authorize\(|ExportController|ReorderTasks" app routes tests
```

## Expected result

Every project-scoped read and write has one auditable membership scope and one explicit policy decision.

## Suggested commit boundaries

- `test: define collaboration access and policy matrix`
- `feat: replace owner-only scopes with membership access`
- `test: close nested route and export authorization gaps`

## Next action

Lock the role/policy and cross-project route tests, then implement the access scopes before starting [DYX-003](DYX-003-membership-invitations.md).
