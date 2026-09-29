# PlanOps Role-change Activity Verification

Date: 2026-09-29

## Delivered

- Added a project-scoped `ProjectActivityFeedQuery` for `MEMBER_ROLE_CHANGED` events.
- Added a Project activity panel to the project overview for active project members.
- Rendered actor, subject, old role, new role, and timestamp without raw metadata.
- Preserved the existing append-only `ProjectEvent` model and role-change action contract.
- Added focused feature, domain, browser, removed-viewer, timestamp, and serious/critical axe coverage.
- Corrected the existing light/system theme contrast for project attention indicators.

The slice does not merge project events into the global task-activity feed and does
not render the remaining project event types.

## Verification

```text
php artisan test --no-ansi --compact
```

Result: 346 passed, 3 skipped, 1,681 assertions.

The three existing skips are environment-scoped concurrency checks:

- `tests/Feature/Collaboration/AssignmentConcurrencyTest.php`
- `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`
- `tests/Feature/Collaboration/TaskCreationConcurrencyTest.php`

```text
php artisan view:cache
npm.cmd run build
npm.cmd run test:browser
```

All three commands passed. The browser suite reports 8 passed, including the
project role-change journey with serious/critical axe checks.

Documentation link validation and `git diff --check` also passed. The Laravel
run retains the repository's known non-failing warnings for non-compound `use`
statements in existing tests.

## Release boundary

This verification covers the role-change activity slice only. PostgreSQL,
broader P0/P1 release criteria, and concurrency gates remain open as documented
in the Sprint 2 release boundary.
