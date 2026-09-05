# DYX-000 — Contract and baseline freeze

**Status:** Accepted — repeatable baseline; pre-existing failures remain visible

**Priority:** P0 release gate

**Dependency:** None

**Owner:** PlanOps engineering

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 1.1, 1.2, 57, and 70.1. [Baseline evidence](../baselines/2026-09-05-sprint-2-baseline.md).

## Goal

Freeze one authoritative collaboration contract and record the repository baseline before schema or authorization changes begin.

## Scope

- Reconcile the Sprint 2 authority document with `planops-complete-spec.md`, `docs/architecture/domain-contracts.md`, `docs/architecture/stack.md`, `docs/ui/screen-spec.md`, and the earlier implementation plan.
- Record the current PHP test, frontend build, route list, and relevant configuration baselines.
- Name pre-existing failures and decide whether each is fixed before feature work or explicitly quarantined with an owner and release impact.
- Confirm that the backlog index and the detailed implementation plan use the same P0/P1/P2 boundary and dependency order.

## Tasks

### Task DYX-000.1

Goal: Capture reproducible test, build, route, and toolchain baselines.

Files: `docs/PlanOps_Sprint_2.md`, `docs/backlogs/README.md`, CI configuration, and the test/build configuration files.

Action: Run `php artisan test --no-ansi --compact`, `npm.cmd run build`, and `php artisan route:list --except-vendor`. Record command, date, exit code, summary, and environment in the release notes or linked issue.

Why: later failures must be attributable to the collaboration work rather than an unknown starting state.

Verification: Repeat each command from a clean checkout or CI-equivalent environment and compare the summaries.

Expected result: One dated baseline is available. The current 2026-09-05 PHP inventory is 64 failed, 186 passed, and 1 skipped; the frontend build and route inventory passed. Two fresh full-suite runs produced the same count after deterministic sort fixtures were added.

### Task DYX-000.2

Goal: Reconcile authority documents and terminology.

Files: `docs/PlanOps_Sprint_2.md`, `planops-complete-spec.md`, `docs/architecture/domain-contracts.md`, `docs/architecture/stack.md`, `docs/ui/screen-spec.md`, and `docs/superpowers/plans/2026-08-20-planops-implementation.md`.

Action: Search for conflicting owner-only access, invitation status, role, label ownership, queue, route, activity actor, export, and profile-deletion claims. Update or explicitly mark each conflicting statement as superseded.

Why: implementation cannot be reviewed consistently while multiple documents define different security contracts.

Verification: Run targeted `rg` searches for `ownedBy`, `project_memberships`, `assignee_id`, `actor_user_id`, `project_events`, and the canonical role/event values. Review every hit that describes behavior.

Expected result: The authority document, architecture docs, UI contract, and implementation plan agree on the collaboration vocabulary and release boundary.

### Task DYX-000.3

Goal: Convert baseline failures into explicit release decisions.

Files: `docs/backlogs/README.md`, the relevant test files, and the project issue tracker or release checklist.

Action: Classify every baseline failure as `fix-before-P0`, `quarantine-with-owner`, or `unrelated-blocker`. Do not hide failures by weakening assertions or skipping tests.

Why: a known red baseline is safe only when its scope and release treatment are explicit.

Verification: Each failure has a named location, cause or hypothesis, owner, follow-up item, and release decision. The full suite remains the final gate.

Expected result: DYX-001 can begin without an ambiguous test baseline.

## Acceptance criteria

- [ ] The five baseline documents and the earlier plan have been compared against the authority document.
- [ ] Current test, build, and route outputs are dated and reproducible.
- [ ] Every pre-existing failure has a named treatment; no failure is silently ignored.
- [ ] The backlog index, Sprint 2 plan, and implementation plan agree on P0, P1, P2, and dependency order.
- [x] No code migration or access-layer change started until this gate was accepted.

## Current evidence

- [x] PHP, build, route, and toolchain evidence is recorded in [the dated baseline](../baselines/2026-09-05-sprint-2-baseline.md).
- [x] All 64 failures from the latest inventory run have named test-file locations and an initial `fix-before-P0` classification.
- [x] The pre-collaboration documents now carry explicit historical-baseline notices that point to the Sprint 2 authority.
- [x] Explain and eliminate the one-test variance: random task numbers and implicit sort fixture dates caused an order-sensitive `task_key` case.

## Verification commands

```text
php artisan test --no-ansi --compact
npm.cmd run build
php artisan route:list --except-vendor
rg -n "ownedBy|project_memberships|assignee_id|actor_user_id|project_events" app routes tests docs
```

## Expected result

One accepted contract and one visible baseline. This backlog does not make the PHP suite green by itself; it makes the existing red baseline measurable and governed.

## Suggested commit boundary

`docs: freeze Sprint 2 contract and baseline`

## Next action

Review and accept DYX-000, then start [DYX-001](DYX-001-schema-migration.md). Keep the 64-failure baseline visible until each failure has a resolution or an explicitly approved quarantine.
