# PlanOps Collaboration Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the first high-risk collaboration slice explicit and safe: new projects create a durable Owner identity, Admins can create tasks in accessible projects, detailed analytics and full exports enforce the Owner/Admin boundary, and period selectors submit to the report they control.

**Architecture:** Preserve legacy `user_id` columns for compatibility while dual-writing the collaboration identity fields already introduced by Sprint 2. Put authorization in policies/actions/query scopes, keep writes transactional, and use focused feature tests as the contract. Do not add new schema in this slice.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Pest, Blade, PostgreSQL-compatible query builder, existing ProjectRole enum and collaboration models.

**Spec:** `docs/PlanOps_Sprint_2.md` sections 11–14, 21–23, 35, 66.1 and the DYX-002/DYX-003 backlog contracts.

## Global Constraints

- Keep `Project::accessibleBy()` as the read boundary; do not reintroduce owner-only route assumptions.
- Owner/Admin is the only role allowed to create tasks, view detailed project analytics, or export complete project data.
- Members retain read access to accessible projects and tasks, but a global export must omit projects they cannot export rather than leaking mixed-role rows.
- Every new production behavior starts with a failing Pest test (TDD), then the smallest implementation that makes the test pass.
- Preserve existing legacy tests and fields unless a test documents an intentional contract change.
- Run focused tests after each task and the full verification suite before reporting completion.

## Task PLN-001 — Make project creation establish collaboration identity

- [ ] **Goal:** Every newly created project has `owner_id` equal to the creating user and exactly one active `OWNER` membership for that user, written atomically.
- **Files:** `tests/Feature/Collaboration/ProjectCreationIdentityTest.php` (new), `app/Domain/Projects/Actions/CreateProject.php`, and only the minimum model/factory files required by a failing test.
- **Action:**
  1. Add a failing action-level test that creates a project through `CreateProject`, then asserts `owner_id`, `user_id` compatibility, and the active `ProjectMembership` role/user/joined timestamp.
  2. Add a failure-path test using a database constraint/exception seam proving the project and membership do not commit independently.
  3. Wrap project creation and Owner membership creation in one `DB::transaction`; set `owner_id` on the project and create the membership with `ProjectRole::OWNER` and `joined_at` from the persisted project.
  4. Run the focused project and collaboration tests, then the existing project-management tests.
- **Why:** Existing projects are backfillable, but new projects currently enter the system without the membership row that every collaboration policy assumes.
- **Verification:** `php artisan test --compact tests/Feature/Collaboration/ProjectCreationIdentityTest.php tests/Unit/Domain/Projects/ProjectKeyTest.php tests/Feature/Projects/ProjectManagementTest.php`.
- **Expected result:** A newly created project is immediately safe for membership-aware reads and policy checks; a partial creation cannot leave an ownerless project or orphan membership.

## Task PLN-002 — Allow Admin task creation and persist creator identity

- [ ] **Goal:** An active project Admin can create a task and the task records the authenticated creator without weakening parent or project boundaries.
- **Files:** `tests/Feature/Collaboration/AdminTaskCreationTest.php` (new), `app/Domain/Tasks/Actions/CreateTask.php`, `app/Http/Controllers/TaskController.php` (only if the create-form parent selector is proven to be inconsistent), and any focused task test fixture updates.
- **Action:**
  1. Add a failing test for an Admin with an active `ProjectMembership::ADMIN` row creating a task through the action; assert the project number, `created_by_user_id` actor, legacy `user_id` compatibility, and actor-aware creation activity.
  2. Add a failing test that an Admin can select a top-level parent from the same accessible project, while a parent from another project remains rejected.
  3. Change the locked project lookup from legacy `user_id` ownership to `Project::accessibleBy($user)` plus the project key, while retaining the existing policy authorization and transaction lock.
  4. Dual-write `created_by_user_id` for every new task; retain the legacy `user_id` value needed by current reads until the later cutover.
  5. Update the task-create parent query only when needed so the Admin sees valid same-project top-level parents without exposing cross-project rows.
  6. Run focused collaboration/task-creation tests and the task-number concurrency regression tests.
- **Why:** The current action authorizes Admins but then locks projects and parents by `user_id`, so the policy and transaction disagree and Admin creation fails.
- **Verification:** `php artisan test --compact tests/Feature/Collaboration/AdminTaskCreationTest.php tests/Feature/Tasks/CreateTaskTest.php tests/Feature/Tasks/TaskNumberConcurrencyTest.php tests/Feature/Collaboration/PolicyMatrixTest.php`.
- **Expected result:** Owner and Admin task creation share one membership-aware transaction; creator identity is never inferred from project ownership.

## Task PLN-003 — Close detailed analytics and complete-export authorization gaps

- [ ] **Goal:** Members cannot open detailed project analytics or export full data, and mixed-role global exports include only projects where the viewer has export permission.
- **Files:** `tests/Feature/Analytics/AnalyticsScreenTest.php`, `tests/Feature/Export/ExportTest.php`, `tests/Feature/Collaboration/MemberExperienceTest.php`, `app/Http/Controllers/ProjectAnalyticsController.php`, `app/Domain/Projects/Models/Project.php`, `app/Policies/ProjectPolicy.php`, and `app/Domain/Export/Queries/ExportQueryService.php`.
- **Action:**
  1. Add a failing analytics request test for an active Member who can read the project but receives a 403 from the detailed analytics route; retain the existing foreign-project 404 test.
  2. Add failing export tests for a member-only viewer (403) and a mixed-role viewer whose export contains the Owner/Admin project but omits the member-only project, tasks, and activity.
  3. Add a reusable `Project::exportableBy()` scope (Owner/Admin membership, plus the legacy no-membership compatibility path) and use it from `ProjectPolicy::exportAny()` and all three export queries.
  4. Authorize `viewAnalytics` in `ProjectAnalyticsController` after route binding; keep the route binding as the read/access check.
  5. Update the conflicting member export expectation to the Sprint 2 privacy contract and run the complete export/analytics/collaboration test set.
- **Why:** The current controller never calls `viewAnalytics`, and `ExportQueryService` exports every accessible project after only a global “any export” check, which leaks member-only projects for mixed-role users.
- **Verification:** `php artisan test --compact tests/Feature/Analytics/AnalyticsScreenTest.php tests/Feature/Export/ExportTest.php tests/Feature/Collaboration/MemberExperienceTest.php tests/Feature/Collaboration/PolicyMatrixTest.php`.
- **Expected result:** Detailed analytics and complete exports have a single, testable Owner/Admin boundary and no mixed-role leakage.

## Task PLN-004 — Make period selectors submit to the active report

- [ ] **Goal:** Dashboard, global analytics, and project analytics period controls submit to their own route while preserving the selected period values.
- **Files:** `tests/Feature/Analytics/AnalyticsScreenTest.php`, `resources/views/components/dashboard/period-selector.blade.php`, `resources/views/pages/dashboard/index.blade.php`, `resources/views/pages/analytics/index.blade.php`, and `resources/views/pages/projects/analytics.blade.php`.
- **Action:**
  1. Add failing HTML assertions that global analytics uses `route('analytics')` and project analytics uses `route('projects.analytics', $project)` as the form action; retain a dashboard assertion for `route('dashboard')`.
  2. Give the shared component an explicit `action` prop with the dashboard route as its default, and pass the correct action from each analytics view.
  3. Keep the existing period/from/until field names and selected values unchanged so `DashboardPeriodRequest` remains the single validation boundary.
  4. Run analytics/dashboard feature tests and render/cache the views.
- **Why:** The shared component currently always targets `/dashboard`, so applying a period from either analytics surface silently navigates away and changes the wrong report.
- **Verification:** `php artisan test --compact tests/Feature/Analytics/AnalyticsScreenTest.php tests/Feature/Dashboard/DashboardPeriodTest.php`; then `php artisan view:cache`.
- **Expected result:** Period selection is predictable, accessible, and route-correct across all three reporting surfaces.

## Final verification gate

- [ ] Run the focused tests for all four tasks.
- [ ] Run `php artisan test --compact` from a fresh process.
- [ ] Run `php artisan view:cache` and `npm.cmd run build`.
- [ ] Inspect `git diff --check` and the final diff for accidental scope expansion.
- [ ] Record any pre-existing Pint failures separately; do not claim formatting is clean unless the formatter test passes.
