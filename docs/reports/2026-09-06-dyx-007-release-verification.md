# DYX-007 Release Verification

Date: 2026-09-06

## Scoped Sprint 2 evidence

The DYX-006 notification, collaboration, label, task-list, and export checks
pass. The scoped matrix recorded 58 passed tests, 1 expected PostgreSQL-only
skip, and 217 assertions. The isolated PostgreSQL assignment race also passed
with 1 test and 6 assertions.

## Full-suite result

Command:

```text
php artisan test --no-ansi --compact
```

Result: 236 passed, 65 failed, 2 skipped, 1261 assertions.

The failures are not in the scoped DYX-006 matrix. The main baseline groups
are:

- unit tests that use Laravel factories without the Laravel application
  bootstrap (`A facade root has not been set`);
- legacy expectations that do not include the new `ASSIGNEE_CHANGED` activity
  enum value;
- older dashboard, project-console, My Work, and task-identity contracts that
  predate the collaboration access model.

The full run also emits existing non-compound `use` warnings in test files.

## Build and static checks

```text
php artisan view:cache       PASS
npm.cmd run build             PASS
git diff --check              PASS
```

## Release decision

DYX-007 is not approved for a clean full-suite release yet. DYX-006 scoped
evidence is green, but the overall gate remains red until the 65 baseline
failures are either corrected or explicitly accepted as known issues with an
owner and follow-up. No new migration, notification, or collaboration scope
failure was observed in the scoped verification matrix.
