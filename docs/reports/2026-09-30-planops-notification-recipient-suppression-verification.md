# PlanOps Notification Recipient Suppression Verification

Date: 2026-09-30

## Delivered

- Re-fetch the notification recipient when the delivery job runs.
- Suppress all delivery for deactivated or deleted recipients before
  persistence or mail delivery.
- Require an active project membership before delivering an assignment outcome.
- Preserve the existing safe-target redaction behavior for active recipients.

This change does not decide whether PlanOps should send a separate
member-removal message. That product and privacy decision remains deferred.

## Verification

```text
php artisan test tests/Feature/Notifications/NotificationDeliveryTest.php --no-ansi
```

Result: 6 passed, 12 assertions.

The focused tests cover revoked invitation target redaction, removed assignment
recipients, delayed reassignment, deactivated recipients, bounded failure
metadata, and delivery to a still-authorized recipient.

## Release boundary

This closes the notification recipient-suppression contract referenced by
DYX-006. PostgreSQL migration evidence, broader deactivated-account access
enforcement, and the separate P2 member-removal notification decision remain
open in the release gate.
