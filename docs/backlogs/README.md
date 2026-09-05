# PlanOps Sprint 2 Backlog Index

**Purpose:** turn the Sprint 2 specification into small, independently reviewable workstreams.

**Authority:** [PlanOps Sprint 2 Implementation Plan](../PlanOps_Sprint_2.md)

**Detailed implementation plan:** [Sprint 2 collaboration implementation plan](../superpowers/plans/2026-09-05-planops-sprint-2-collaboration-implementation.md)

**Created:** 2026-09-05

This folder is an execution tracker. It does not replace the authority document. If a backlog item and the authority document disagree, stop and reconcile the documents before writing code.

## Current release boundary

The release is split into three boundaries:

| Boundary | Backlogs | Outcome |
| --- | --- | --- |
| P0 — Collaboration Foundation | DYX-000 through DYX-005 | Secure membership access, invitations, ownership transfer, assignment, My Work, actor history, and project-scoped labels |
| P1 — Collaboration Experience | DYX-006 plus the P1 companion items below | After-commit notifications, notification center, assignee filters, collaboration-aware exports, dashboard counts, and pending-invitation UI |
| P2 — Deferred | P2 items below | Team Work, team analytics, additional activity notifications, and realtime delivery |

P1 work starts only after the P0 security and migration gates are green. P2 work must not start while P0 remains incomplete or while the release gate has unapproved failures.

## Backlog map

| ID | Priority | Depends on | Reviewable result | Backlog |
| --- | --- | --- | --- | --- |
| DYX-000 | P0 gate | — | One accepted authority set and a recorded baseline | [Contract and baseline](DYX-000-contract-baseline.md) |
| DYX-001 | P0 | DYX-000 | Safe schema, invariants, and idempotent migration | [Schema and migration](DYX-001-schema-migration.md) |
| DYX-002 | P0 | DYX-001 | Membership-aware query scopes, policies, bindings, and route security | [Access and policies](DYX-002-access-policies.md) |
| DYX-003 | P0 | DYX-002 | Invitation, membership, removal, role, and ownership-transfer lifecycle | [Membership and invitations](DYX-003-membership-invitations.md) |
| DYX-004 | P0 | DYX-003 | One-assignee task flow, actor-aware history, and assignment-based My Work | [Assignment and My Work](DYX-004-assignment-my-work.md) |
| DYX-005 | P0 | DYX-001 and DYX-002 | Project-scoped labels with safe legacy-data handling | [Project-scoped labels](DYX-005-labels.md) |
| DYX-006 | P1 | DYX-003 and DYX-004 | Observable, retryable notification delivery after commit | [Notifications](DYX-006-notifications.md) |
| DYX-007 | Release gate | DYX-005 and DYX-006 | Verified implementation and reconciled contracts | [Release verification](DYX-007-release-verification.md) |

The critical path is `DYX-000 → DYX-001 → DYX-002 → DYX-003 → DYX-004 → DYX-006 → DYX-007`. `DYX-005` may run in parallel with DYX-003 and DYX-004 after DYX-001 and DYX-002 are green, but DYX-007 waits for it.

## P1 companion backlog

These items are P1 scope from the authority document. They are intentionally tracked here because they cross more than one DYX workstream.

### S2-P1-001 — Notification persistence and delivery

- [ ] Add database notification persistence after the business transaction commits. Owner: DYX-006.
- [ ] Deliver invitation and assignment email through the existing queue contract with bounded retries. Owner: DYX-006.
- [ ] Add idempotency keys, failure logging, retention, and target reauthorization. Owner: DYX-006.

### S2-P1-002 — Notification center

- [ ] Add unread count and notification list routes/components.
- [ ] Add mark-read and mark-all-read actions with authorization tests.
- [ ] Keep notification links accessible and safe after membership removal.

### S2-P1-003 — Assignee filters

- [ ] Add assignee filters to the project task list.
- [ ] Add assignee filters to the project board.
- [ ] Preserve current sorting, pagination, and membership scope.
- [ ] Defer team-wide filters until the P2 Team Work decision is approved.

### S2-P1-004 — Collaboration-aware exports

- [ ] Require the explicit `export` ability before project/task export.
- [ ] Scope exported rows through the same membership-aware query path as the UI.
- [ ] Test direct URLs, cross-project identifiers, archived projects, and role boundaries.

### S2-P1-005 — Collaboration-aware dashboard counts

- [ ] Count only projects and tasks visible to the authenticated viewer.
- [ ] Keep member-facing progress in project Overview without exposing performance rankings.
- [ ] Add Owner/Admin-only analytics and export assertions.

### S2-P1-006 — Pending invitation experience

- [ ] Show a safe pending invitation state without exposing invitation tokens or project data.
- [ ] Support resend/revoke state transitions already defined by DYX-003.
- [ ] Cover expired, revoked, accepted, and duplicate pending invitations.

## P2 deferred backlog

P2 is not part of the P0/P1 release gate. Create implementation plans only after the P0 authorization and migration gates are green.

### S2-P2-001 — Team Work

- [ ] Define the Team Work information architecture and member visibility rules.
- [ ] Reuse assignment and membership scopes; do not introduce a second authorization model.

### S2-P2-002 — Role-change activity

- [ ] Render `MEMBER_ROLE_CHANGED` in the project activity surface.
- [ ] Preserve actor identity and append-only history.

### S2-P2-003 — Member-removal notification

- [ ] Decide whether removal notifications are required and what target data is safe.
- [ ] Suppress delivery to deactivated or removed recipients.

### S2-P2-004 — Team analytics

- [ ] Define aggregate-only metrics and an explicit Owner/Admin permission boundary.
- [ ] Prohibit member-level performance rankings unless separately approved.

### S2-P2-005 — Realtime notification delivery

- [ ] Select a transport only after the database/email notification contract is stable.
- [ ] Keep the after-commit and idempotency guarantees from DYX-006.

## Cross-cutting rules

- Every backlog item must name its files, dependency, acceptance criteria, and verification evidence.
- Active membership means `removed_at IS NULL`. Removed membership must stop access immediately.
- There is exactly one active `OWNER` membership per project. `projects.owner_id` must agree with it.
- `OWNER` is a membership role, not an invitation role.
- `tasks.created_by_user_id` identifies the creator; nullable `tasks.assignee_id` identifies the current assignee; the actor is the authenticated user performing the mutation.
- Every project/task read, route binding, selector, export, search result, activity feed, analytics query, and dashboard count must use the required access scope.
- Archived projects are read-only. Removed or deactivated users cannot mutate through stale pages or direct requests.
- Cross-record changes that affect access or assignment must lock and revalidate membership inside the transaction.
- Do not log raw invitation tokens. Store only a secure token hash and use the documented seven-day expiry.
- Database/email notifications must be dispatched after commit and must not change the P0 business outcome when delivery fails.
- Preserve exact technical identifiers from the authority document, including enum values, model names, route names, migration fields, and event names.

## Definition of Ready

A backlog item is ready when:

- its dependency is green;
- its files and interfaces are named;
- its authorization and data-scope rules are explicit;
- its acceptance tests are listed;
- its migration or rollback impact is understood; and
- no unresolved contradiction remains in the authority documents.

## Definition of Done

A backlog item is done when:

- implementation and tests satisfy every acceptance criterion;
- direct URL, cross-project, removed-member, archived-project, and role-boundary paths are covered where relevant;
- migration, concurrency, and after-commit behavior are verified where relevant;
- documentation and route/UI contracts are synchronized; and
- the change is saved in a focused, reviewable commit.

## Working sequence

1. Start with [DYX-000](DYX-000-contract-baseline.md).
2. Move to the next dependency only after the current backlog has evidence for its acceptance criteria.
3. Keep P0 implementation and security tests ahead of P1 polish.
4. Use [DYX-007](DYX-007-release-verification.md) as the release checklist, not as a substitute for earlier tests.

**Next action:** execute DYX-000 by recording the current test, build, route, and document baselines.
