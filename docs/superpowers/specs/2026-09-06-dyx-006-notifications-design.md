# DYX-006 Notifications Design

## Status

Proposed for review.

## Goal

Add safe, observable invitation and assignment notifications without making
delivery part of the membership or task transaction. Notifications must only
describe committed outcomes, must not expose invitation secrets, and must
remain useful when membership or target access changes before delivery.

## Scope

The first implementation covers:

- invitation-created notifications for the invited recipient;
- assignee-changed notifications for the new assignee;
- database persistence and queued delivery after commit;
- deterministic duplicate suppression and bounded retry behavior;
- recipient-scoped list, unread count, mark-read, and mark-all-read actions;
- target authorization rechecks when delayed work is processed.

Realtime/WebSocket delivery, push notifications, and notification fan-out to
unrelated project members are out of scope.

## Domain contracts

Each notification outcome has a stable event name, project ID, recipient ID,
safe target type/ID, and deterministic idempotency key. Invitation payloads
contain the invitation ID and project display data, never the raw token.
Assignment payloads contain the task ID, project ID, actor ID, previous
assignee ID, and new assignee ID. Display names and email addresses are
resolved at render time where possible; secrets are never serialized.

The event names are `INVITATION_CREATED` and `ASSIGNEE_CHANGED`, matching the
existing audit vocabulary while remaining independent of audit rows.

## Data flow

1. A collaboration action completes its existing database transaction.
2. The action records a domain outcome and registers an after-commit job.
3. The job inserts or upserts the recipient-scoped notification using its
   idempotency key.
4. Database and mail channels are delivered from the persisted, redacted
   payload according to configured channel policy.
5. Processing reauthorizes the recipient and target. Removed/deactivated
   recipients, revoked invitations, deleted tasks, or archived projects are
   suppressed safely.
6. Failures are retried with bounded backoff and recorded without secrets.

The business action remains successful if notification delivery fails.
Rollback means no notification job is released and no notification row is
created.

## Persistence and retry rules

Use Laravel’s notification persistence conventions with an additional
idempotency key or delivery-outcome table as needed to enforce duplicate
suppression. The uniqueness boundary is the recipient plus event key, not the
target display text. Jobs use a finite attempt count and explicit backoff.
Failure records contain event name, recipient ID, target IDs, attempt count,
and a sanitized error class/message; they never contain invitation tokens or
full serialized credentials.

## Authorization and UI

All notification queries are filtered by the authenticated recipient. Target
links are generated only for currently accessible projects/tasks; otherwise
the item remains readable as a historical message with no unsafe link.

The notification center adds a bell/unread count, an accessible list, mark
read, and mark all read. Controls use normal links/forms, keyboard-visible
focus, and live status text for state changes. Duplicate mark-read requests
are harmless.

## Implementation boundaries

- `app/Domain/Notifications`: event value objects, persistence/query service,
  and delivery job/listener contracts.
- `app/Notifications`: Laravel channel notifications for database/email.
- collaboration actions: emit only the two stable domain outcomes after the
  existing transaction succeeds.
- notification controller/routes/views: recipient-scoped center and read
  mutations.
- migrations/models: notification records, idempotency, and sanitized
  failure metadata.

Existing membership, task, and project policies remain canonical. No parallel
permission checks are introduced in notification controllers or jobs.

## Verification plan

Tests will prove:

- contract payloads, recipients, safe targets, and secret redaction;
- no delivery before commit and no delivery after rollback;
- business state survives a delivery exception;
- duplicate jobs are suppressed and retries stop at the configured bound;
- removed/deactivated recipients and inaccessible targets are suppressed;
- notification rows and read mutations are recipient-scoped;
- unread counts, mark-read, mark-all-read, keyboard labels, and live status
  messaging render correctly.

The existing focused collaboration suite must remain green. The full legacy
suite remains a separate baseline gate because it currently contains unrelated
pre-existing failures.

## Risks and decisions

Database notifications are preferred because the project already uses Laravel
and has a database queue contract. Mail delivery is an adapter over the same
redacted notification payload and must not be required for the notification
center. A notification that loses authorization is suppressed rather than
rewritten into a new target, preventing access resurrection.
