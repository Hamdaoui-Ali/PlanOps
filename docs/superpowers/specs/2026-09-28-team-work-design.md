# Team Work Design

**Date:** 2026-09-28

## Goal

Give project Owners and Admins a calm workload view that answers who has open,
blocked, overdue, and recently completed work without turning collaboration into
employee ranking.

## Scope

- Add a project-scoped Team Work route at `/projects/{project}/team/work`.
- Allow only active project Owners and Admins to view it.
- Show one row per active project member with:
  - active work (`IN_PROGRESS` and `IN_REVIEW`);
  - blocked work (`BLOCKED`);
  - overdue non-terminal work;
  - work completed during the current viewer-local week.
- Show a separate unassigned open-work count.
- Count only top-level, non-deleted tasks so subtasks do not inflate workload.
- Reuse the viewer's stored timezone and the existing membership/task access
  boundaries.
- Add links from the project overview and Team page; keep membership management
  on the existing Team surface.

## Visibility and privacy

Team Work is an operational manager surface, not a performance report. It will
not expose productivity scores, rankings, percentages, cycle-time comparisons,
or member-level analytics beyond the listed workload counts. Members receive a
403 response and do not get a partial view of other people's workload.

## Data flow

`ProjectTeamWorkController` authorizes `viewTeamWork`, then delegates to
`TeamWorkQuery`. The query resolves the project through
`Project::accessibleBy($viewer)`, loads active memberships, and computes the
counts from the project-scoped top-level task set. The Blade view renders the
returned read model and contains no authorization logic beyond visibility of
links already enforced by the policy.

## Interaction and accessibility

- Use the existing PlanOps project-page typography, buttons, tables, and focus
  styles.
- Give the workload table an accessible caption and row/member labels.
- Make the Team Work link keyboard reachable from both source surfaces.
- On narrow screens, allow horizontal table scrolling with a visible label;
  do not hide metrics behind hover-only controls.

## Verification

- Query tests cover metric definitions, current-week timezone boundaries,
  removed members, unassigned work, subtasks, deleted tasks, and foreign
  projects.
- HTTP tests cover Owner/Admin access, Member denial, direct foreign-project
  URLs, rendered metrics, and the no-ranking contract.
- Browser/axe smoke tests visit the page with seeded Owner/Admin data and
  verify keyboard-reachable navigation, visible table labels, and zero serious
  accessibility violations.
