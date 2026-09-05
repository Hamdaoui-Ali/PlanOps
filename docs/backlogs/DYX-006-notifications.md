# DYX-006 — After-commit notifications and P1 collaboration experience

**Status:** Deferred until P0 business outcomes are stable

**Priority:** P1

**Dependencies:** [DYX-003](DYX-003-membership-invitations.md) and [DYX-004](DYX-004-assignment-my-work.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 45–50, 51, 56, 57, 65, 68, and 70.1.

## Goal

Add observable, retryable invitation and assignment notifications after the P0 transaction commits, then deliver the smallest useful notification-center experience without coupling delivery failures to business outcomes.

## Files

- notification migrations/models
- notification classes, domain events, listeners, and queue jobs
- `composer.json` and `.env.example` only if the existing mail/queue contract requires configuration changes
- notification routes, controllers/components, and views
- invitation and assignment actions/listeners
- notification, after-commit, idempotency, and accessibility tests

## Tasks

### Task DYX-006.1

Goal: Define the notification event and recipient contract.

Files: notification classes/events/listeners, invitation and assignment actions, and contract tests.

Action: Define invitation-created and assignee-changed domain outcomes with stable identifiers, recipient rules, safe target URLs, and the data needed for database and email channels. Use the exact event names already defined for project audit history; do not couple user-facing delivery to the audit table.

Why: delivery channels need a stable contract that can evolve without changing the P0 actions.

Verification: Contract tests assert recipient, project/task scope, target authorization, idempotency key, and payload redaction for each event.

Expected result: Notification consumers receive enough data to render safe messages without querying unscoped records.

### Task DYX-006.2

Goal: Dispatch notifications only after successful commit.

Files: actions, listeners/jobs, queue configuration, and transaction tests.

Action: Register after-commit delivery for accepted invitations and successful assignment changes. Ensure a rolled-back transaction sends no notification and a delivery failure does not roll back the membership or assignment outcome.

Why: notifications are side effects and must not become a second transaction boundary for P0 security state.

Verification: Use transaction tests and faked notification/mail channels to prove no delivery before commit, no delivery after rollback, and preserved business state after a delivery exception.

Expected result: Notifications describe committed outcomes only.

### Task DYX-006.3

Goal: Make delivery idempotent, retryable, and observable.

Files: notification persistence/job/listener classes, failure logging, retention configuration, and tests.

Action: Add idempotency keys, bounded retry/backoff, failure records or logs without secrets, retention rules, and duplicate suppression. Reauthorize the recipient and target when processing a delayed notification; suppress delivery to removed or deactivated users.

Why: retries and delayed jobs are normal, while membership can change between event creation and delivery.

Verification: Test duplicate jobs, retry exhaustion, removed recipient, deactivated recipient, revoked invitation, deleted task, target project archive, and safe retry logging.

Expected result: Delivery can be retried and diagnosed without duplicate spam, token leakage, or access resurrection.

### Task DYX-006.4

Goal: Deliver the notification center and unread state.

Files: notification model/migration, routes/controllers/components/views, and notification feature tests.

Action: Add the notification bell/unread count, list, mark-read, and mark-all-read actions. Scope every query to the authenticated recipient and keep target links subject to current authorization.

Why: database notifications are useful only when users can inspect and clear them safely.

Verification: Request/browser tests cover unread counts, read transitions, duplicate requests, removed/deactivated users, cross-user IDs, keyboard navigation, focus, and live status messages.

Expected result: Users can view and manage their own notifications without seeing another user's records or a now-inaccessible target.

### Task DYX-006.5

Goal: Add the remaining P1 collaboration experience items when their owners are green.

Files: project task list/board filters, export controller/query, dashboard queries/views, invitation Team views, and their tests.

Action: Implement assignee filters, collaboration-aware export authorization/scoping, collaboration-aware dashboard counts, and pending invitation state UI. Reuse DYX-002 access scopes and DYX-003/004 domain actions; do not create parallel permission logic.

Why: these P1 items cross several domains but must present one consistent collaboration model.

Verification: Test role boundaries, direct URLs, cross-project identifiers, removed membership, archived projects, pagination/sorting, and accessible filter/status controls.

Expected result: P1 surfaces reflect the same membership, assignment, and authorization rules as P0.

## Acceptance criteria

- [ ] Invitation and assignment notifications are emitted only after the originating transaction commits.
- [ ] Delivery failure never rolls back a successful P0 membership or assignment outcome.
- [ ] Idempotency, bounded retries, failure logging, retention, and target reauthorization are tested.
- [ ] Removed/deactivated recipients cannot receive or follow an unauthorized target link.
- [ ] Raw invitation tokens and other secrets never appear in notification payloads or logs.
- [ ] Notification list/read actions are recipient-scoped and authorization-protected.
- [ ] Assignee filters, exports, dashboard counts, and pending invitation UI reuse canonical access scopes.
- [ ] WebSockets/realtime delivery remains out of scope for this backlog.

## Verification commands

```text
php artisan test --filter=Notification
php artisan test --filter=AfterCommit
php artisan test --filter=Export
php artisan test --filter=Dashboard
npm.cmd run build
```

## Expected result

P1 notification and collaboration-experience features are decoupled from P0 state changes, safe to retry, and scoped to current membership.

## Suggested commit boundaries

- `test: define after-commit notification contracts`
- `feat: add idempotent invitation and assignment notifications`
- `feat: add notification center and collaboration filters`
- `test: verify notification retries and target reauthorization`

## Next action

Do not start DYX-006 until DYX-003 and DYX-004 have accepted P0 outcomes and concurrency evidence.
