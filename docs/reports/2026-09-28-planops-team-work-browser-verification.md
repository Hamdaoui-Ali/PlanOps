# PlanOps Team Work and Browser Verification

Date: 2026-09-28

## Delivered

- Added the owner/admin-only Team Work workload surface for project managers.
- Added viewer-local week and overdue calculations, active-member metrics, and
  separate unassigned open-work counts.
- Added project overview and Team links to the new surface.
- Added an isolated Playwright runtime with deterministic SQLite seeding.
- Added public landing/login accessibility checks, authenticated Team Work
  checks, keyboard focus coverage, and serious/critical axe checks.
- Corrected the light/system theme active-navigation contrast issue found by
  the new browser audit.

## Browser verification

Command:

```text
npm.cmd run test:browser
```

Result: 5 passed.

The run rebuilds Vite assets, creates
`storage/framework/testing/browser.sqlite`, migrates and seeds the browser
fixtures, starts Laravel on port 8001, and removes the database during
teardown. The browser server uses `--no-reload` so Laravel forwards the
isolated database environment on Windows; cookie sessions preserve login
across the built-in server's request processes.

## Application verification

```text
php artisan view:cache       PASS
npm.cmd run build             PASS
git diff --check              PASS
```

Full Laravel suite:

```text
php artisan test --compact
```

Result: 336 passed, 3 skipped, 1616 assertions.

The three existing skips are environment-specific concurrency checks:

- `tests/Feature/Collaboration/AssignmentConcurrencyTest.php`
- `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`
- `tests/Feature/Collaboration/TaskCreationConcurrencyTest.php`

They require PostgreSQL or `proc_open`; no new failure was introduced.

## Release decision

The Team Work feature and browser verification harness are green on
`feature/planops-browser-qa-team-work`. No push or merge was performed.
