# Team Work Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a project-scoped Owner/Admin Team Work surface that reports workload without ranking people.

**Architecture:** Keep membership authorization in `ProjectPolicy`, keep reads in a collaboration query service, and return a small typed read model to Blade. Reuse `Project::accessibleBy()`, top-level task scope, `UserPeriodResolver`, and the existing PlanOps project-page patterns.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Pest 4, Blade, existing CSS/Alpine UI.

**Spec:** `docs/superpowers/specs/2026-09-28-team-work-design.md` and Sprint 2 sections 44 and 66.

## Global Constraints

- Active membership means `removed_at IS NULL`.
- Team Work is Owner/Admin-only and must not expose productivity scores or rankings.
- Count top-level, non-deleted tasks only.
- Active work means `IN_PROGRESS` and `IN_REVIEW`; blocked work is reported separately.
- Overdue means the viewer-local date is after `due_on` and the task is not `DONE` or `CANCELLED`.
- Completed-this-week uses the viewer's configured timezone and week-start preference.
- Archived projects remain readable but no mutation is introduced by this feature.
- Every production behavior starts with a failing Pest test.

## Review Focus

- Removed memberships must disappear from the member rows and cannot be used to view the route.
- A Member must receive 403 and no partial workload payload.
- Tasks from another project, subtasks, deleted tasks, and terminal tasks must not inflate counts.
- Sunday week-start and timezone boundaries must include/exclude completions correctly.
- Empty projects must render a useful zero-state without a ranking or productivity claim.

### Task 1: Define the Team Work read model and query

**Files:**
- Create: `app/Domain/Collaboration/ValueObjects/TeamWorkMember.php`
- Create: `app/Domain/Collaboration/ValueObjects/TeamWorkSnapshot.php`
- Create: `app/Domain/Collaboration/Queries/TeamWorkQuery.php`
- Test: `tests/Unit/Domain/Collaboration/TeamWorkQueryTest.php`

**Interfaces:**
- Produces `TeamWorkQuery::for(User $viewer, Project $project, ?CarbonImmutable $now = null): TeamWorkSnapshot`.
- `TeamWorkSnapshot::$members` is a collection of `TeamWorkMember` rows and exposes `unassignedCount`, `weekStart`, and `weekEnd`.
- `TeamWorkMember` exposes user identity, role, `activeCount`, `blockedCount`, `overdueCount`, and `completedThisWeekCount`.

- [ ] Write failing tests for all five review-focus cases and the normal Owner/Admin member fixture.
- [ ] Run the focused unit test and verify it fails because the read model/query is missing.
- [ ] Implement the immutable read models and query using the existing access scope and `UserPeriodResolver`.
- [ ] Run `php artisan test --compact tests/Unit/Domain/Collaboration/TeamWorkQueryTest.php` and verify the expected metrics.
- [ ] Commit as `feat: add Team Work workload query`.

### Task 2: Add policy, route, and controller boundary

**Files:**
- Modify: `app/Policies/ProjectPolicy.php`
- Create: `app/Http/Controllers/Collaboration/ProjectTeamWorkController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Collaboration/TeamWorkHttpTest.php`

**Interfaces:**
- Produces the `viewTeamWork(User $user, Project $project): bool` policy ability.
- Produces route `projects.team.work` at `/projects/{project}/team/work`.

- [ ] Write failing HTTP tests for Owner/Admin access, Member 403, foreign-project URL, and empty-state data.
- [ ] Run the focused HTTP test and verify the route/ability is missing or denied.
- [ ] Add the policy ability, controller, and route; authorize before querying.
- [ ] Run `php artisan test --compact tests/Feature/Collaboration/TeamWorkHttpTest.php tests/Feature/Collaboration/PolicyMatrixTest.php`.
- [ ] Commit as `feat: protect Team Work for project managers`.

### Task 3: Build the Team Work Blade surface and navigation links

**Files:**
- Create: `resources/views/pages/projects/team-work.blade.php`
- Modify: `resources/views/pages/projects/show.blade.php`
- Modify: `resources/views/pages/projects/team.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/Collaboration/TeamWorkHttpTest.php`

- [ ] Extend the failing HTTP assertions to cover accessible headings, table labels, metric values, unassigned copy, and absence of ranking language.
- [ ] Implement the page using existing PlanOps project headers, buttons, table patterns, focus styles, and a mobile horizontal-scroll wrapper.
- [ ] Add Team Work links only when `viewTeamWork` is authorized.
- [ ] Run the focused HTTP tests plus `php artisan view:cache`.
- [ ] Commit as `feat: add Team Work workload surface`.

### Task 4: Verify the full Team Work slice

- [ ] Run the complete collaboration and task query test matrix.
- [ ] Run `php artisan test --no-ansi --compact`, `php artisan view:cache`, and `npm.cmd run build`.
- [ ] Inspect `git diff --check` and add a focused release note if the browser harness consumes this route.
- [ ] Commit any documentation-only verification update separately as `docs: record Team Work verification`.
