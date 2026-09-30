# PlanOps Notification Recipient Suppression Verification

Date: 2026-09-30

## Delivered

- Re-fetch the notification recipient when the delivery job runs.
- Suppress all delivery for deactivated or deleted recipients before
  persistence or mail delivery.
- Require an active project membership or a validated legacy owner identity
  before delivering an assignment outcome.
- Preserve the existing safe-target redaction behavior for active recipients.
- Redact an existing notification target when a retry discovers that the
  recipient is now deactivated or removed.
- Persist successful mail delivery and suppress duplicate queued jobs for the
  same notification idempotency key.
- Give every mail attempt a stable hashed `Message-ID` derived from the same
  idempotency key.
- Preserve assignment delivery for a valid legacy project owner before the
  collaboration backfill creates an owner membership.
- Lock the recipient and relevant invitation, task, and membership rows while
  authorizing and delivering the outcome.

This change does not decide whether PlanOps should send a separate
member-removal message. That product and privacy decision remains deferred.

## Verification

```text
php artisan test tests/Feature/Notifications/NotificationDeliveryTest.php --no-ansi
```

Result: 11 passed, 26 assertions.

The focused tests cover revoked invitation target redaction, removed assignment
recipients, delayed reassignment, deactivated recipients, retry-time target
redaction, bounded failure metadata, mail-failure persistence, duplicate queued
mail suppression, legacy owner delivery, and delivery to a still-authorized
recipient.

The full PHP suite also passes with 356 tests, 3 environment-scoped skips, and
1,706 assertions. The database seeder now supplies explicit fixture metadata so
the release fixture reproducibility contract is not affected by shared Faker
state from preceding tests.

The application suppresses duplicate queued jobs and emits a stable provider-
visible mail identity. A generic SMTP timeout after provider acceptance remains
inherently ambiguous; an end-to-end no-duplicate guarantee requires a mail
provider that honors the stable message or an equivalent provider idempotency
key.

## Release boundary

This closes the application-side notification recipient-suppression contract
referenced by DYX-006. PostgreSQL migration evidence, broader
deactivated-account access enforcement, provider-level mail idempotency, and
the separate P2 member-removal notification decision remain open in the release
gate.
