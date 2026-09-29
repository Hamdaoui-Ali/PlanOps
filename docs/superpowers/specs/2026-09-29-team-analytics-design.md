# Team Analytics Design

**Date:** 2026-09-29
**Status:** Approved concept; implementation spec submitted for review
**Scope:** Project-scoped Team Analytics for Owners and Admins

## Goal

Add a project-scoped Team Analytics surface that helps project managers understand
aggregate work flow and current attention areas without turning PlanOps into a
people-ranking or productivity-scoring tool.

Team Analytics complements the existing Team Work page:

- Team Work answers: "What needs attention in the project right now?"
- Team Analytics answers: "What aggregate work moved during the selected period,
  and what is the current project workload shape?"

## Chosen approach

Use a dedicated route and read model:

```text
GET /projects/{project}/team/analytics
    -> ProjectTeamAnalyticsController
    -> TeamAnalyticsQuery
    -> TeamAnalyticsSnapshot
    -> pages.projects.team-analytics
```

This keeps the operational Team Work query separate from period-based analytics
and avoids adding a view switch with ambiguous permissions to the existing project
analytics page.

The existing `ProjectPolicy::viewAnalytics` ability is the authorization boundary.
The Sprint 2 contract defines project/team analytics as an Owner/Admin capability,
so a second role or permission is not introduced for this slice.

## Alternatives considered

1. Add a `?view=team` mode to `/projects/{project}/analytics`.
   This reduces route count but couples two read models and makes the page's
   scope less obvious.
2. Add a combined Team Work and Team Analytics page.
   This would mix current-state workload with historical period facts and make the
   page harder to scan.
3. Add `/projects/{project}/team/analytics` with a dedicated query and snapshot.
   **Chosen.** The route states the audience and scope, the two surfaces remain
   independently testable, and future analytics can evolve without changing Team
   Work's privacy contract.

## Audience and privacy contract

The page is visible only to active project Owners and Admins through
`viewAnalytics`. Members receive the existing forbidden response for detailed
analytics. Inaccessible or foreign projects must not reach the query or render a
report.

The first version is aggregate-only:

- no member names or avatars;
- no counts grouped by assignee;
- no per-member tables, rankings, leaderboards, scores, or percentiles;
- no language claiming hours worked, utilization, effort, or productivity;
- no raw activity feed or actor details;
- no new export or realtime behavior.

Created-vs-completed and other flow counts describe project scope movement. They
must be labeled as work flow, never as employee performance.

## Data contract

`TeamAnalyticsQuery::for(User $viewer, Project $project, ReportPeriod $period)`
returns a `TeamAnalyticsSnapshot` containing:

- `project`: the authorized project;
- `reportPeriod`: the resolved viewer-local period;
- `throughput`: distinct top-level task counts for `created`, `completed`,
  `started`, `reviewed`, `blocked`, and `reopened` during the selected period;
- `statusDistribution`: current counts of non-deleted, top-level,
  non-cancelled tasks by status;
- `currentWorkload`: current aggregate counts for `active`, `blocked`,
  `overdue`, and `unassigned` tasks;
- `weeklyFlow`: viewer-timezone week buckets inside the selected period, with
  aggregate `created`, `completed`, and `blocked` counts;
- `hasRecordedMovement`: whether the selected period contains any counted flow
  event.

### Scope rules

- Load only tasks belonging to the authorized project.
- Exclude deleted tasks and subtasks from all first-version metrics.
- Do not resolve or group by a member record for presentation.
- Use `TaskActivity` as the event source for period throughput and weekly flow.
- Use the task's current status, due date, and assignee for current workload.
- Count an overdue task only when it is past its due date and not terminal, using
  the same overdue semantics as Team Work.
- Count an unassigned task when its current `assignee_id` is null.
- Resolve report bounds with `UserPeriodResolver`; week buckets must use the
  viewer's display timezone and retain the explicit selected period in the view.
- Do not add a migration or background worker for this slice.

The first version deliberately omits median lead/cycle time and reconstructed
time-in-status charts. Existing release work still calls for stronger period
boundary verification, and Team Analytics must not amplify uncertain duration
semantics.

## Page structure

Use the selected light editorial visual direction: warm light canvas, dark navy
text and navigation, thin borders, restrained amber accents, generous spacing,
and short explanatory labels. Reuse existing PlanOps dashboard and project
analytics components/classes where possible.

1. **Header**
   - breadcrumb or project context;
   - project name and "Team Analytics" heading;
   - an explicit "Owners & Admins only" access label;
   - short copy: "Aggregate project flow and current workload attention.";
   - period selector and selected-period banner;
   - links to Project overview and Team Work.
2. **Summary cards**
   - Completed in selected period;
   - Blocked now;
   - Overdue now;
   - Unassigned now.

   Each card must state whether its value is period-based or current-state.
3. **Status distribution panel**
   Show aggregate status counts for the current non-cancelled task scope. A
   segmented visual may be used, but the table/text values are the accessible
   source of truth.
4. **Weekly flow panel**
   Show created, completed, and blocked counts by viewer-timezone week. If a chart
   is used, provide the same values in a semantic table or text summary.
5. **Privacy/help panel**
   Explain that the view contains aggregate project data only and does not show
   individual metrics or rankings.
6. **No-data states**
   - selected period with no events: "No recorded team movement in this period.";
   - current scope with no work: explain that current workload is empty and link
     back to Team Work.

## Accessibility and responsive behavior

- Use semantic headings, table captions, row/column headers, and `scope` values.
- Charts must have a table or text alternative; color cannot be the only status
  signal.
- Keep labels short, sentence case, and high contrast with comfortable line
  height.
- Preserve visible keyboard focus and support the existing reduced-motion rules.
- Keep the period selector and navigation controls keyboard reachable.
- On narrow screens, allow the weekly-flow table to scroll in a focusable region;
  do not hide data behind hover or pointer-only controls.
- Verify both light and dark themes if shared dashboard styles affect the page.

## Implementation boundaries

Expected files or equivalents:

- `app/Domain/Analytics/Queries/TeamAnalyticsQuery.php`;
- `app/Domain/Analytics/ValueObjects/TeamAnalyticsSnapshot.php`;
- `app/Http/Controllers/ProjectTeamAnalyticsController.php`;
- `resources/views/pages/projects/team-analytics.blade.php`;
- `routes/web.php`;
- `tests/Unit/Domain/Analytics/TeamAnalyticsQueryTest.php`;
- `tests/Feature/Analytics/TeamAnalyticsScreenTest.php`;
- `tests/Browser/collaboration.spec.js` or a focused browser spec.

The query may share carefully tested event/status helpers with the existing
analytics service, but it must not expose the existing global/project snapshot
   directly or reuse Team Work's current-state read model as a historical report.

## Verification contract

Before the feature is considered complete, tests must cover:

- distinct lifecycle counts within the selected period;
- project isolation and deleted/subtask exclusion;
- current status, blocked, overdue, and unassigned counts;
- viewer-timezone weekly buckets and explicit period rendering;
- Owner and Admin access;
- Member forbidden access;
- foreign-project rejection;
- no member-level identifiers in rendered HTML;
- accessible table/text alternatives and correct period-selector action;
- Owner browser navigation, keyboard reachability, and axe checks;
- Member browser denial with no analytics content exposed.

The full repository test suite, production build, view cache, and browser suite
remain required before claiming completion. The release gate remains open for
the unrelated PostgreSQL, migration, and broader P0/P1 evidence gaps already
recorded in DYX-007.

## Explicit non-goals

- member-level workload breakdown;
- productivity scoring or employee ranking;
- role-change activity feed;
- member-removal notifications;
- realtime updates;
- analytics export;
- cross-project or global team aggregation;
- corrections to the existing duration/time-in-status semantics.
