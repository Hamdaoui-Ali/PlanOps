# Team Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a project-scoped, Owner/Admin-only Team Analytics dashboard with aggregate flow metrics and current workload attention counts.

**Architecture:** Add a dedicated `TeamAnalyticsQuery` and `TeamAnalyticsSnapshot` under the analytics domain. Expose them through a project-scoped controller and route, then render a Blade dashboard that keeps period-based flow separate from current-state workload. Reuse the existing policy, period request, dashboard components, PlanOps CSS, and browser harness; add no migration, package, or background worker.

**Tech Stack:** Laravel 13, PHP 8.3+, Blade, Eloquent, CarbonImmutable, Pest 4, Playwright, axe-core, Vite.

**Spec:** `docs/superpowers/specs/2026-09-29-team-analytics-design.md`

## Global Constraints

- The page is visible only to active project Owners and Admins through `viewAnalytics`.
- The first version is aggregate-only: no member names or avatars, no counts grouped by assignee, no per-member tables, rankings, leaderboards, scores, or percentiles.
- No language may claim hours worked, utilization, effort, or productivity.
- Exclude deleted tasks and subtasks from all first-version metrics.
- Use `TaskActivity` as the event source for period throughput and weekly flow.
- Resolve report bounds with `UserPeriodResolver`; week buckets use the viewer's display timezone.
- Do not add a migration or background worker for this slice.
- Charts must have a table or text alternative; color cannot be the only status signal.
- The release gate remains open for the unrelated PostgreSQL, migration, and broader P0/P1 evidence gaps already recorded in DYX-007.

## Review Focus

- **Period versus current state:** selected-period flow must not be confused with current blocked, overdue, or unassigned counts; pin the labels and values in the query and feature view tests.
- **Timezone and week boundaries:** an event near UTC midnight must fall into the viewer's local week; pin this in the query test with a non-UTC preference.
- **Scope leakage:** foreign-project tasks, deleted tasks, and subtasks must not affect the snapshot; pin this in the query test.
- **Authorization and binding:** Owner/Admin access succeeds, Member access is forbidden, removed members are not resolved, and foreign projects are not resolved; pin this in the feature test.
- **Aggregate privacy and accessible data:** rendered HTML must contain no member identifiers and every visual trend/status summary must have a semantic table or text alternative; pin this in feature and browser tests.

---

### Task 1: Build the Team Analytics read model and query

**Goal:** Produce a deterministic aggregate snapshot for one authorized project and one resolved report period.

**Files:**
- Create: `app/Domain/Analytics/ValueObjects/TeamAnalyticsSnapshot.php`
- Create: `app/Domain/Analytics/Queries/TeamAnalyticsQuery.php`
- Create: `tests/Unit/Domain/Analytics/TeamAnalyticsQueryTest.php`
- Reference: `app/Domain/Analytics/Queries/AnalyticsQueryService.php`
- Reference: `app/Domain/Collaboration/Queries/TeamWorkQuery.php`
- Reference: `app/Domain/Tasks/Models/Task.php`
- Reference: `app/Domain/Tasks/Rules/OverdueTask.php`

**Interfaces:**
- `TeamAnalyticsSnapshot::__construct(Project $project, ReportPeriod $reportPeriod, array $throughput, array $statusDistribution, array $currentWorkload, Collection $weeklyFlow, bool $hasRecordedMovement)`.
- `TeamAnalyticsQuery::for(User $viewer, Project $project, ReportPeriod $period, ?CarbonImmutable $now = null): TeamAnalyticsSnapshot`.
- `throughput` always contains integer keys `created`, `completed`, `started`, `reviewed`, `blocked`, and `reopened`.
- `statusDistribution` contains integer counts keyed by `TaskStatus` values `BACKLOG`, `NOT_STARTED`, `IN_PROGRESS`, `IN_REVIEW`, `BLOCKED`, and `DONE`; `CANCELLED` is excluded from this current non-cancelled scope.
- `currentWorkload` contains integer keys `active`, `blocked`, `overdue`, and `unassigned`; `active` means `IN_PROGRESS` plus `IN_REVIEW`, while `overdue` and `unassigned` exclude terminal tasks.
- `weeklyFlow` is a `Collection<int, array{label:string,created:int,completed:int,blocked:int}>`, with one viewer-timezone week row per bucket intersecting the selected period.

**Action:** Implement the query as a project-scoped read model. Re-resolve the project through `Project::detailedReportsVisibleTo($viewer)` before loading data. Load non-deleted, top-level tasks for the project, then load matching `TaskActivity` rows inside `[period.start, period.end)`. Count each lifecycle fact by distinct task ID using the same status transitions as `AnalyticsQueryService`; use current task state and `Task::isOverdueOn()` for the attention counts. Build week buckets from the viewer preference timezone and week-start preference, comparing activity timestamps in UTC after assigning each event to its local week.

**Why:** Keeping this query separate prevents the current-state Team Work model from becoming a historical report and avoids exposing the existing global/project analytics snapshot as a broader API.

- [ ] **Step 1: Write the failing query tests.** Add tests named:
  - `test('counts distinct project flow and current aggregate workload')` - create duplicate lifecycle events, active/blocked/done/unassigned tasks, then assert the exact throughput, status distribution, current workload, `hasRecordedMovement`, and weekly flow counts.
  - `test('excludes subtasks deleted tasks terminal attention and foreign project data')` - create each excluded variant and assert that neither current counts nor period flow changes.
  - `test('buckets activity by the viewer timezone and week start preference')` - use a user preference such as `America/New_York`, place events around a UTC date boundary, and assert their local-week rows and labels.
- [ ] **Step 2: Run the focused tests to verify they fail.**

  Run: `php artisan test tests/Unit/Domain/Analytics/TeamAnalyticsQueryTest.php --no-ansi --compact`

  Expected: FAIL because `TeamAnalyticsQuery` and `TeamAnalyticsSnapshot` do not exist.
- [ ] **Step 3: Implement `TeamAnalyticsSnapshot` and `TeamAnalyticsQuery::for(...)`.** Keep event classification private to the query, initialize all required metric keys to zero, use `whereNull('parent_task_id')`, rely on Eloquent's default soft-delete scope for tasks, and filter activity through the loaded task IDs so deleted/subtask/foreign activity cannot enter the report.
- [ ] **Step 4: Run the focused tests to verify they pass.**

  Run: `php artisan test tests/Unit/Domain/Analytics/TeamAnalyticsQueryTest.php --no-ansi --compact`

  Expected: PASS with all query tests green.
- [ ] **Step 5: Commit the read model.**

  ```bash
  git add app/Domain/Analytics/Queries/TeamAnalyticsQuery.php app/Domain/Analytics/ValueObjects/TeamAnalyticsSnapshot.php tests/Unit/Domain/Analytics/TeamAnalyticsQueryTest.php
  git commit -m "feat: add team analytics read model"
  ```

**Verification:** Focused Pest tests pass and the snapshot contains no member-level collection or identifier.

**Expected result:** A deterministic, aggregate-only `TeamAnalyticsSnapshot` exists with tested period flow, current attention, status distribution, and timezone-aware weekly flow.

---

### Task 2: Expose the project-scoped route with authorization

**Goal:** Make the Team Analytics report reachable only through the existing project analytics policy boundary.

**Files:**
- Create: `app/Http/Controllers/ProjectTeamAnalyticsController.php`
- Create: `tests/Feature/Analytics/TeamAnalyticsScreenTest.php`
- Modify: `routes/web.php:63-72`

**Interfaces:**
- Route name: `projects.team.analytics`.
- Route path: `/projects/{project}/team/analytics`.
- Controller method: `index(DashboardPeriodRequest $request, Project $project, UserPeriodResolver $periods, TeamAnalyticsQuery $analytics): View`.
- The controller passes `project`, `snapshot`, and `selection` to `pages.projects.team-analytics`.
- Authorization is `Gate::forUser($request->user())->authorize('viewAnalytics', $project)` before resolving the report.

**Action:** Add the route inside the existing authenticated group near the Team Work and project analytics routes. Implement the controller using the same period-selection flow as `ProjectAnalyticsController`, resolving `DashboardPeriodRequest::selection()` through `UserPeriodResolver` and passing the result to `TeamAnalyticsQuery`.

**Why:** The route and controller establish the security boundary before the UI adds discoverability. Existing project route binding already limits resolution to accessible projects; the policy adds the Owner/Admin analytics rule.

- [ ] **Step 1: Write the failing feature tests.** Add tests named:
  - `test('owners and admins can open project team analytics with a selected period')` - assert 200, the `projects.team.analytics` period form action, `Team Analytics`, the project name, and `Month`.
  - `test('members and removed members cannot open project team analytics')` - assert Member `403` and removed member `404`.
  - `test('a foreign project is not resolved through the project team analytics route')` - assert `404`.
- [ ] **Step 2: Run the focused feature tests to verify they fail.**

  Run: `php artisan test tests/Feature/Analytics/TeamAnalyticsScreenTest.php --no-ansi --compact`

  Expected: FAIL because the route, controller, and view are not present.
- [ ] **Step 3: Implement the route and controller.** Use the exact route name, path, signature, authorization call, and view data contract above. Keep the controller thin; do not calculate metrics there.
- [ ] **Step 4: Add the smallest valid Blade view shell required for the route to render.** The complete semantic dashboard is Task 3; this step only needs the heading and period selector needed to prove the route contract.
- [ ] **Step 5: Run the focused feature tests to verify they pass.**

  Run: `php artisan test tests/Feature/Analytics/TeamAnalyticsScreenTest.php --no-ansi --compact`

  Expected: PASS with Owner/Admin success and Member/removed/foreign denial cases green.
- [ ] **Step 6: Commit the route boundary.**

  ```bash
  git add app/Http/Controllers/ProjectTeamAnalyticsController.php routes/web.php tests/Feature/Analytics/TeamAnalyticsScreenTest.php resources/views/pages/projects/team-analytics.blade.php
  git commit -m "feat: expose project team analytics route"
  ```

**Verification:** The focused feature tests prove the route resolves only accessible projects and applies the Owner/Admin policy.

**Expected result:** `projects.team.analytics` is a working, authorized route with a stable controller/view data contract.

---

### Task 3: Build the light editorial Team Analytics dashboard

**Goal:** Render the selected visual direction with clear period/current-state labels, aggregate-only content, and accessible data alternatives.

**Files:**
- Modify: `resources/views/pages/projects/team-analytics.blade.php`
- Modify: `resources/views/pages/projects/team-work.blade.php`
- Modify: `resources/views/pages/projects/analytics.blade.php`
- Modify: `resources/views/pages/projects/show.blade.php`
- Modify: `resources/css/app.css`
- Modify: `tests/Feature/Analytics/TeamAnalyticsScreenTest.php`

**Interfaces:**
- The page heading ID is `team-analytics-heading`.
- Summary metric wrappers use `data-metric="completed"`, `data-metric="blocked"`, `data-metric="overdue"`, and `data-metric="unassigned"`.
- The status table uses `team-analytics-status-table`; the flow table uses `team-analytics-flow-table`; the privacy panel uses `team-analytics-privacy`.
- The period selector action is `route('projects.team.analytics', $project)`.
- Navigation links use `route('projects.team.analytics', $project)` and are rendered only inside `@can('viewAnalytics', $project)` where the source surface is not already policy-protected.

**Action:** Replace the shell with a PlanOps light editorial dashboard: project context, `Team Analytics` heading, explicit `Owners & Admins only` label, period selector, selected-period banner, four summary cards, current status distribution table/visual, viewer-timezone flow table, privacy/help panel, and links to Project overview and Team Work. Keep the page free of member names, avatars, assignee groupings, and performance language. Add responsive styles that reuse dashboard variables, maintain visible focus, and provide horizontal scrolling for the flow table on narrow screens.

**Why:** The visual direction is calm and data-first, while the semantic tables make the dashboard usable without a chart renderer or color perception.

- [ ] **Step 1: Extend the feature tests with content and privacy assertions.** Assert the four metric labels, `Selected period`, `Current workload`, the status and flow table hooks, the privacy copy, `No recorded team movement in this period.` for an empty period, and `assertDontSee` for a member name/email. Assert the Team Analytics links from Project overview, Project Analytics, and Team Work for an authorized viewer.
- [ ] **Step 2: Run the focused feature tests to verify the new assertions fail.**

  Run: `php artisan test tests/Feature/Analytics/TeamAnalyticsScreenTest.php --no-ansi --compact`

  Expected: FAIL because the shell lacks the full dashboard, privacy boundary, and navigation links.
- [ ] **Step 3: Implement the Blade structure and navigation links.** Use the existing `x-dashboard.period-selector`, `x-dashboard.kpi-card`, `dashboard-period-banner`, and `dashboard-data-table` patterns. Keep current-state counts visibly labeled as `Now` and selected-period flow visibly labeled as `Selected period`.
- [ ] **Step 4: Add focused Team Analytics CSS.** Add page/grid/table/status-bar styles near the existing analytics and Team Work styles in `resources/css/app.css`; include breakpoints for the existing 900px/640px dashboard behavior and a focusable overflow region for the flow table.
- [ ] **Step 5: Run the focused feature tests and view cache.**

  Run: `php artisan test tests/Feature/Analytics/TeamAnalyticsScreenTest.php --no-ansi --compact`

  Expected: PASS with no member identifiers rendered.

  Run: `php artisan view:cache`

  Expected: PASS with compiled Blade views.
- [ ] **Step 6: Commit the dashboard UI.**

  ```bash
  git add resources/views/pages/projects/team-analytics.blade.php resources/views/pages/projects/team-work.blade.php resources/views/pages/projects/analytics.blade.php resources/views/pages/projects/show.blade.php resources/css/app.css tests/Feature/Analytics/TeamAnalyticsScreenTest.php
  git commit -m "feat: add team analytics dashboard"
  ```

**Verification:** Feature assertions cover period/current-state semantics, privacy, empty data, navigation, and the accessible table alternatives.

**Expected result:** Owners/Admins see a calm, aggregate-only Team Analytics dashboard that matches the selected light editorial direction and links cleanly from existing project surfaces.

---

### Task 4: Verify the browser journey and accessibility boundary

**Goal:** Prove the dashboard works in the real seeded browser flow for an Owner and remains unavailable to a Member.

**Files:**
- Modify: `database/seeders/BrowserSeeder.php` only if deterministic activity rows are needed for selected-period flow assertions.
- Modify: `tests/Browser/collaboration.spec.js`

**Interfaces:**
- Browser route: `${projectPath}/team/analytics`.
- Owner journey starts with `loginAs(page, 'browser-owner@example.test')` and resolves the project via `browserProjectPath(page)`.
- Member journey starts with `loginAs(page, 'browser-member@example.test')` and expects HTTP `403` with no `Team Analytics` heading.

**Action:** Add a Playwright test that opens Team Analytics from the project surface, checks the URL and heading, checks the four aggregate metric regions and privacy note, confirms keyboard focus reaches the Team Work link, and runs the existing serious/critical axe helper. Add a separate Member denial test. Keep assertions aggregate-only; do not add a browser assertion for a member row or name.

**Why:** The browser harness is the release evidence for navigation, focus, rendered accessibility, and the authorization boundary.

- [ ] **Step 1: Add the red browser tests.** Extend `tests/Browser/collaboration.spec.js` with Owner and Member Team Analytics scenarios using the existing helpers.
- [ ] **Step 2: Run the focused browser spec to verify it fails.**

  Run: `npm.cmd run test:browser -- tests/Browser/collaboration.spec.js`

  Expected: FAIL on the missing Team Analytics link/route or missing page assertions.
- [ ] **Step 3: Add only the deterministic BrowserSeeder activity/task data required by the assertions.** Preserve the existing Team Work counts (`unassigned` remains `1`, active member count remains `3`) and avoid adding member-level analytics data to the page.
- [ ] **Step 4: Run the focused browser spec to verify it passes.**

  Run: `npm.cmd run test:browser -- tests/Browser/collaboration.spec.js`

  Expected: PASS for the existing Team Work tests plus the new Owner Team Analytics and Member denial tests.
- [ ] **Step 5: Commit the browser evidence.**

  ```bash
  git add tests/Browser/collaboration.spec.js database/seeders/BrowserSeeder.php
  git commit -m "test: cover team analytics browser journey"
  ```

**Verification:** Playwright and axe checks pass, keyboard focus is visible/reachable, and Members receive no analytics document.

**Expected result:** The Team Analytics browser journey is reproducible and included in the collaboration verification suite.

---

### Task 5: Reconcile the product documentation and backlog

**Goal:** Record the delivered Team Analytics slice without closing unrelated release gates.

**Files:**
- Modify: `docs/ui/screen-spec.md`
- Modify: `docs/architecture/stack.md`
- Modify: `docs/architecture/domain-contracts.md`
- Modify: `docs/PlanOps_Sprint_2.md`
- Modify: `docs/backlogs/README.md`
- Modify: `docs/backlogs/DYX-007-release-verification.md`

**Interfaces:**
- Documented route: `GET /projects/{project}/team/analytics`.
- Documented permission: Owner/Admin through `viewAnalytics`; Member sees Overview progress but not detailed team analytics.
- Documented boundary: aggregate flow and current workload only; no productivity scoring, rankings, member breakdown, realtime delivery, or global aggregation.

**Action:** Add the route to the UI screen map, describe the light editorial dashboard and its semantic table alternatives, update the architecture collaboration overlay, and mark only the Team Analytics first-version checklist item as delivered. Keep DYX-007 PostgreSQL, migration, broader P0/P1, and release-exception items open. Link the design spec and browser evidence where the documents already link feature evidence.

**Why:** The repository's backlog and architecture documents are release authorities; documentation must distinguish a delivered P2 slice from the still-open release gate.

- [ ] **Step 1: Add documentation assertions before editing.** Verify with `rg`/PowerShell that the route map does not yet contain `projects/{project}/team/analytics`, the P2 Team Analytics item is unchecked, and the current release gate wording remains open.
- [ ] **Step 2: Update each document with the exact route, permission, privacy, and evidence language above.** Do not mark the overall DYX-007 gate complete.
- [ ] **Step 3: Verify the reconciliation.**

  Run: `rg -n "team/analytics|Team Analytics|aggregate|productivity|DYX-007" docs/ui/screen-spec.md docs/architecture/stack.md docs/architecture/domain-contracts.md docs/PlanOps_Sprint_2.md docs/backlogs/README.md docs/backlogs/DYX-007-release-verification.md`

  Expected: every authority document records the same route and boundary, the Team Analytics checklist is checked, and the unrelated release gaps remain visible.

  Run: `git diff --check`

  Expected: PASS with no whitespace errors.
- [ ] **Step 4: Commit the documentation reconciliation.**

  ```bash
  git add docs/ui/screen-spec.md docs/architecture/stack.md docs/architecture/domain-contracts.md docs/PlanOps_Sprint_2.md docs/backlogs/README.md docs/backlogs/DYX-007-release-verification.md
  git commit -m "docs: record team analytics delivery"
  ```

**Verification:** Route, permission, privacy, and release-gate language agree across the named authority documents.

**Expected result:** Future work can distinguish Team Analytics delivery from the remaining release blockers.

---

### Task 6: Run the complete verification gate

**Goal:** Verify the feature and the existing application together before claiming completion.

**Files:**
- No intended file changes.

**Action:** Run the full repository tests, production build, view compilation, and browser suite. Preserve and report any pre-existing PHP warnings without treating a zero exit code as a warning-free run.

- [ ] **Step 1: Run the full Pest suite.**

  Run: `php artisan test --no-ansi --compact`

  Expected: PASS; record the exact passed/skipped/assertion counts and any non-failing warnings.
- [ ] **Step 2: Run the production build.**

  Run: `npm.cmd run build`

  Expected: PASS.
- [ ] **Step 3: Compile Blade views.**

  Run: `php artisan view:cache`

  Expected: PASS.
- [ ] **Step 4: Run the browser suite.**

  Run: `npm.cmd run test:browser`

  Expected: PASS, including the Team Analytics Owner/Member journeys.
- [ ] **Step 5: Confirm the branch state.**

  Run: `git status --short --branch`

  Expected: clean working tree; commits are ready for the user-requested push when requested.

**Verification:** All commands exit successfully, the feature evidence is visible in the browser, and no unrelated release claim is overstated.

**Expected result:** Team Analytics is implemented, tested, documented, and ready for final branch integration review.
