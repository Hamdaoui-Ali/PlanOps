# DYX-006 Verification Report

Date: 2026-09-06

## Verified

- Notification outcome contracts are redacted and idempotent.
- Invitation and assignment delivery is released after commit.
- Delivery uses bounded retries and sanitized failure records.
- Delayed invitation/task targets are reauthorized before actionable delivery.
- Notification center reads and mutations are recipient-scoped.
- Project task assignee filters use active project members.
- Member task exports and dashboard counts reuse canonical access scopes.
- Pending invitations are visible on the Team surface with status and role.
- Blade templates cache successfully.
- Vite production build succeeds.

## Fresh verification results

Command:

```text
php artisan test tests/Feature/Notifications tests/Feature/Collaboration tests/Feature/Labels/LabelManagementTest.php tests/Feature/Tasks/ProjectTaskListTest.php tests/Feature/Export/ExportTest.php --no-ansi
```

Result: 58 passed, 1 skipped, 217 assertions.

The skipped test is the PostgreSQL-only independent-process assignment race
when the default test connection is SQLite. That race passed separately on an
isolated PostgreSQL 18 cluster with 1 test and 6 assertions.

The earlier redirect query-order and streamed-response assertion failures
were corrected in the verification tests without changing the intended
application behavior.

Additional checks:

```text
php artisan view:cache       PASS
npm.cmd run build             PASS
git diff --check              PASS
```

## Release decision

DYX-006 implementation evidence is present for notification contracts,
after-commit behavior, retry/failure handling, reauthorization, notification
center scope, assignee filters, exports, dashboard counts, and pending
invitation UI. The overall Sprint 2 release gate remains open until the two
baseline failures are either accepted as known issues or corrected.
