# PlanOps Notification Recipient Suppression Verification

Date: 2026-09-30

## Delivered

- Re-fetch the notification recipient when the delivery job runs.
- Suppress all delivery for deactivated or deleted recipients before
  persistence or mail delivery.
- Require an active project membership before delivering an assignment outcome.
- Preserve the existing safe-target redaction behavior for active recipients.
- Redact an existing notification target when a retry discovers that the
  recipient is now deactivated or removed.
- Lock the recipient and relevant invitation, task, and membership rows while
  authorizing and delivering the outcome.

This change does not decide whether PlanOps should send a separate
member-removal message. That product and privacy decision remains deferred.

## Verification

```text
php artisan test tests/Feature/Notifications/NotificationDeliveryTest.php --no-ansi
```

Result: 9 passed, 22 assertions.

The focused tests cover revoked invitation target redaction, removed assignment
recipients, delayed reassignment, deactivated recipients, retry-time target
redaction, bounded failure metadata, mail-failure persistence, and delivery to
a still-authorized recipient.

The full PHP suite also passes with 353 tests, 3 environment-scoped skips, and
1,701 assertions. The database seeder now supplies explicit fixture metadata so
the release fixture reproducibility contract is not affected by shared Faker
state from preceding tests.

## Release boundary

This closes the notification recipient-suppression contract referenced by
DYX-006. PostgreSQL migration evidence, broader deactivated-account access
enforcement, and the separate P2 member-removal notification decision remain
open in the release gate.
