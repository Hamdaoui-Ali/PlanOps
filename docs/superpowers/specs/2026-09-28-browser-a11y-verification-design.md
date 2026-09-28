# Browser and Accessibility Verification Design

**Date:** 2026-09-28

## Goal

Create a repeatable browser verification harness for the collaboration release
gap so UI regressions can be checked against a real Laravel server instead of
only rendered HTML assertions.

## Stack and execution

- Use `@playwright/test` with `@axe-core/playwright`.
- Add a repository-local `playwright.config.js` with a Laravel `webServer`
  command on port `8001`.
- Run browser checks against a dedicated SQLite file at
  `storage/framework/testing/browser.sqlite`; never reuse the developer's
  normal database.
- A Playwright global setup runs migrations and a deterministic browser seeder
  with `APP_ENV=testing`, array cache/session, sync queue, and the browser
  SQLite connection.
- Add an npm script named `test:browser`.

## Seeded journey

The browser seed creates an Owner, Admin, Member, one project, active
memberships, assigned workload in multiple statuses, and an unassigned task.
The smoke suite will:

1. visit the public landing page and login page;
2. log in as the Owner;
3. visit the project Team page and Team Work page;
4. assert the workload metrics and manager navigation are visible;
5. run axe against each authenticated page;
6. verify that the primary navigation and Team Work link can be reached with
   keyboard focus.

The suite will not add test-only production routes or expose credentials in
application logs. The seeded password is test-only and lives in the browser
setup code.

## Failure contract

The command exits non-zero for navigation failures, missing labels, hidden
manager controls, or any serious/critical axe violation. Minor violations are
reported but do not make this first harness a false release blocker; the
release report will include the raw Playwright summary.

## Boundaries

This harness verifies the existing web UI and Team Work flow. It does not
replace Pest authorization tests, does not test external email delivery, and
does not install a browser into the repository.
