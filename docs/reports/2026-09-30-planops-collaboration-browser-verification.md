# PlanOps Collaboration Browser Verification

Date: 2026-09-30

## Delivered

- Added a deterministic pending invitation and recipient notification fixture
  for browser coverage.
- Added an invitation-center journey that verifies the inviter, project, safe
  invitation actions, invitation acceptance, and the resulting project access.
- Added an Owner assignment journey that verifies reassignment to an active
  project Member and the success-state feedback.
- Added a notification read-state journey that verifies the unread badge,
  notification summary, mark-as-read action, and persistent `Read` state.
- Added a Member My Work journey that verifies assignment scoping, a
  project-scoped label, and label filtering without exposing another member's
  work.
- Corrected the notification type color so the rendered notification center
  meets the serious/critical axe contrast gate.

## Browser verification

```text
npm.cmd run test:browser
```

Result: 12 passed.

The run rebuilds Vite assets, creates the isolated SQLite browser database,
migrates and seeds `BrowserSeeder`, starts Laravel on port 8001, and removes
the database during teardown. Playwright runs with one worker and the
configured serious/critical axe checks cover:

- public landing and login accessibility;
- Owner Team Work, Team Analytics, and project role-change activity;
- Member-forbidden Team Work and Team Analytics;
- Member assignment-based My Work and label filtering;
- Owner assignment mutation to an active project Member;
- invitee notification review and invitation acceptance;
- Owner notification read-state feedback; and
- public runtime loading.

## Release boundary

The browser evidence now covers invitation acceptance, assignment mutation,
notification read state, assignment-based My Work, and label filtering.
Mobile-specific collaboration cards, PostgreSQL migration/concurrency proof,
and the broader P0/P1 release criteria remain open in DYX-007.
