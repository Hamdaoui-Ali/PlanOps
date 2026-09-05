# DYX-007 — Release verification and contract reconciliation

**Status:** Blocked until P0 and P1 implementation backlogs are accepted

**Priority:** Release gate

**Dependencies:** [DYX-005](DYX-005-labels.md) and [DYX-006](DYX-006-notifications.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 1.1, 23, 24, 40–43, 52, 57, 58–66, 71, 72, 73, and 76.

## Goal

Prove that the collaboration foundation is safe, complete, and documented before release. This backlog is the final evidence gate; it does not replace the tests owned by DYX-001 through DYX-006.

## Files

- `tests/Feature/`
- `tests/Browser/`
- Playwright and axe configuration if present in the repository
- `docs/architecture/`
- `docs/ui/`
- `docs/PlanOps_Sprint_2.md`
- `docs/backlogs/`
- migration/preflight/concurrency reports
- CI and release-check configuration

## Tasks

### Task DYX-007.1

Goal: Run the complete P0/P1 verification matrix on PostgreSQL.

Files: feature tests, schema/migration tests, policy tests, concurrency tests, notification tests, and CI configuration.

Action: Run fresh migration, legacy-data migration, authorization, invitation, assignment, activity, My Work, label, export, analytics, dashboard, notification, and rollback tests against PostgreSQL. Include duplicate-click and concurrent membership/assignment/ownership cases.

Why: SQLite-only or isolated feature tests cannot prove partial indexes, row locks, JSONB behavior, or production authorization joins.

Verification: Record command, database version/configuration, result, skipped tests, and approved exceptions. No security or concurrency test may be skipped without a named blocking issue.

Expected result: The P0/P1 test matrix has zero unapproved failures and no hidden database-specific gaps.

### Task DYX-007.2

Goal: Verify browser, keyboard, and accessibility contracts.

Files: `tests/Browser/`, Playwright/axe configuration, Team/invitation/task/My Work/notification views, and UI tests.

Action: Exercise the primary user journeys for Owner, Admin, and Member: invite, accept, assign, update assigned task, view My Work, manage labels, and read notifications. Check keyboard navigation, focus after errors, live status messages, mobile Team cards, and hidden Member-ineligible controls.

Why: a server-correct collaboration flow can still be unusable or misleading if the UI hides state or loses focus.

Verification: Run the configured browser and axe checks. If no browser suite is configured, record that as a release gap rather than claiming the gate passed.

Expected result: Core collaboration journeys are usable by keyboard and expose authorization/state changes clearly.

### Task DYX-007.3

Goal: Audit security-sensitive query, route, and identifier paths.

Files: `app/`, `routes/`, `tests/`, and all contract documents.

Action: Search for stale `ownedBy()` access, raw `user_id` ownership assumptions, missing `accessibleBy()` use, unguarded nested routes, unscoped export/search/activity/analytics/dashboard queries, raw invitation-token logging, and direct assignee writes that bypass the action.

Why: access leaks often survive happy-path tests in a secondary surface.

Verification: Review every search hit and add a test or documented intentional exception. Attempt direct requests with valid identifiers from another project and with removed/deactivated membership.

Expected result: Every collaboration surface has an explicit, reviewed access path or a named exception.

### Task DYX-007.4

Goal: Reconcile architecture, UI, implementation, and backlog contracts.

Files: `docs/PlanOps_Sprint_2.md`, `docs/architecture/domain-contracts.md`, `docs/architecture/stack.md`, `docs/ui/screen-spec.md`, `docs/superpowers/plans/2026-08-20-planops-implementation.md`, the current collaboration plan, and `docs/backlogs/`.

Action: Update conflicting claims in the same change set or mark them superseded. Confirm role/event names, invitation rules, label scope, queue behavior, browser requirements, migration assumptions, and P0/P1/P2 boundaries.

Why: release artifacts are part of the implementation contract and must not drift from the code.

Verification: Run documentation searches, link checks, placeholder scans, and a fresh review of the acceptance scenarios.

Expected result: A new engineer can trace each release requirement from the authority document to one backlog and one verification artifact.

### Task DYX-007.5

Goal: Make a release decision with explicit evidence.

Files: release checklist, CI output, migration/preflight/concurrency reports, and backlog files.

Action: Compare every P0/P1 acceptance criterion with evidence. Record approved exceptions, owners, follow-up IDs, and whether P2 may be scheduled. Keep the existing baseline failures visible until they are fixed or formally quarantined.

Why: “done” must mean secure and evidenced, not merely implemented on a happy path.

Verification: Run `php artisan test --no-ansi --compact`, `npm.cmd run build`, configured browser/a11y checks, and the documentation validation commands from the index.

Expected result: The release is approved, or the checklist identifies a specific blocking reason and the next action.

## Acceptance criteria

- [ ] PostgreSQL fresh/legacy migration and rollback evidence is recorded.
- [ ] Full P0/P1 Pest verification has zero unapproved failures and no skipped security/concurrency tests.
- [ ] Browser, keyboard, and axe evidence is present or its absence is recorded as a release gap.
- [ ] Cross-project, removed-member, deactivated-user, archived-project, direct-URL, and concurrent-race cases are covered.
- [ ] No stale owner-only, unscoped query, raw-token, or contract-drift claim remains without an explicit exception.
- [ ] Build and documentation checks pass.
- [ ] Every release exception has a named owner, follow-up ID, and release impact.
- [ ] P2 remains blocked until the P0 security gate is green.

## Verification commands

```text
php artisan test --no-ansi --compact
npm.cmd run build
php artisan route:list --except-vendor
rg -n "ownedBy\(|user_id|accessibleBy\(|assignee_id|actor_user_id|project_events|token" app routes tests docs
```

Run browser/axe commands from the repository configuration when available.

## Expected result

PlanOps can release the collaboration foundation with a traceable evidence set, or the team has a precise, visible blocker rather than an informal “almost done.”

## Suggested commit boundaries

- `test: add Sprint 2 release verification matrix`
- `docs: reconcile collaboration contracts and release evidence`
- `chore: record Sprint 2 release decision`

## Next action

Execute the verification matrix only after DYX-005 and DYX-006 have supplied their acceptance evidence.
