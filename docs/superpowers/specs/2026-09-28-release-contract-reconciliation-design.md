# Release Contract Reconciliation Design

**Date:** 2026-09-28  
**Status:** Approved for implementation  
**Scope:** Release evidence, backlog state, architecture boundaries, and UI route contracts

## Goal

Bring the active PlanOps documentation into agreement with the current
collaboration implementation and verification evidence without overstating the
release gate.

## Current evidence to record

- `php artisan test --compact`: 336 passed, 3 environment-scoped skips, 1616 assertions.
- `npm.cmd run test:browser`: 5 passed.
- `php artisan view:cache`: pass.
- `npm.cmd run build`: pass.
- `git diff --check`: pass.
- The three known skips are PostgreSQL/`proc_open`-dependent concurrency tests:
  `AssignmentConcurrencyTest`, `TaskNumberConcurrencyTest`, and
  `TaskCreationConcurrencyTest`.
- The detailed evidence is recorded in
  `docs/reports/2026-09-28-planops-team-work-browser-verification.md`.

These results are evidence for the current branch, not a claim that every
DYX-007 release criterion is complete.

## Reconciliation decisions

### Release and backlog tracking

- Replace the stale backlog baseline (`301 passed`, `2 skips`, and no browser
  suite) with the current evidence.
- Mark the currently covered browser/keyboard/axe evidence as present while
  leaving PostgreSQL migration, concurrency, and broader P0/P1 matrix criteria
  unchecked.
- Record the initial Team Work slice as delivered: a project-scoped,
  Owner/Admin-only workload view that reuses active membership and assignment
  scopes and does not rank members.
- Keep role-change activity, member-removal notifications, team analytics, and
  realtime delivery deferred.

### Authority document

- Update the Sprint 2 P2 checklist to identify the delivered Team Work slice
  and its limited scope.
- Preserve the rule that remaining P2 work is not part of the P0/P1 release
  gate and remains blocked by unresolved release criteria.

### Architecture document

- Keep the historical single-user baseline for context.
- Add a current Sprint 2 collaboration overlay covering active memberships,
  assignments, notifications, and the delivered Team Work surface.
- State explicitly that team analytics and realtime delivery remain deferred;
  do not imply that the application has WebSockets or productivity scoring.

### UI document

- Add the existing Team and Team Work routes to the route map.
- Replace the owner-only authorization wording with the active-membership and
  policy/query-scope contract.
- Document Team Work as an Owner/Admin manager surface with workload counts,
  active members, unassigned work, keyboard-accessible tables, and no
  productivity ranking.
- Mark only the remaining P2 screens as out of scope; Team Work is the
  implemented exception.

## Non-goals

- No application code, route, policy, query, or visual redesign changes.
- No new Team Analytics metrics.
- No role-change or removal notification behavior.
- No PostgreSQL/concurrency claim beyond the evidence already recorded.
- No Open Design artifact generation.

## Verification contract

The change is complete only when:

1. No active release document says that browser/axe automation is absent.
2. No active backlog document describes the delivered Team Work slice as wholly
   deferred.
3. Historical baseline language is clearly labeled as historical or superseded.
4. Team and Team Work route names match `routes/web.php`.
5. The known skips and remaining release gaps remain explicit.
6. `git diff --check` and the documentation assertions in the implementation
   plan pass.
