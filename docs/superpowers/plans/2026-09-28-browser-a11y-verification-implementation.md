# Browser Accessibility Verification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a repeatable Playwright and axe smoke suite for PlanOps' public, authenticated, and collaboration surfaces.

**Architecture:** Run a Laravel server against a dedicated SQLite file seeded by a deterministic browser seeder. Keep browser-only credentials and setup in test support, use `@playwright/test` for navigation and keyboard assertions, and use `@axe-core/playwright` for serious/critical accessibility checks.

**Tech Stack:** Node.js, Playwright Test, axe-core Playwright adapter, Laravel 13, SQLite test database.

**Spec:** `docs/superpowers/specs/2026-09-28-browser-a11y-verification-design.md`.

## Global Constraints

- Never point browser tests at the developer's normal database.
- Do not add test-only production routes or log browser credentials.
- The browser command must be runnable from a clean checkout after dependency installation.
- Existing Pest tests remain the authorization and domain source of truth.
- Axe failures with serious or critical impact fail the suite; minor findings are reported.

## Review Focus

- A stale browser database must be recreated deterministically on every run.
- Server startup failures must produce a non-zero command instead of hanging.
- Login and authenticated redirects must be asserted before protected-page checks.
- Owner/Admin controls must be visible while Member access to Team Work remains forbidden.
- Keyboard focus must reach navigation and Team Work links without relying on pointer hover.

### Task 1: Add browser dependencies and isolated test runtime

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`
- Create: `playwright.config.js`
- Create: `tests/Browser/global-setup.js`
- Create: `database/seeders/BrowserSeeder.php`
- Modify: `.gitignore`

- [ ] Add the Playwright and axe packages and install the locked dependency graph.
- [ ] Add a failing smoke spec that imports the new test runner and expects the public page to load.
- [ ] Run the browser command and verify the expected missing-config/dependency failure before wiring the runtime.
- [ ] Implement the isolated SQLite path, migration/seed setup, Laravel `webServer`, and cleanup-safe configuration.
- [ ] Run the smoke command until the server boots and the seeded environment is reachable.
- [ ] Commit as `test: add isolated browser test runtime`.

### Task 2: Cover public and authentication accessibility

**Files:**
- Create: `tests/Browser/public-accessibility.spec.js`
- Modify: `package.json` only if a script alias is still needed.

- [ ] Add tests for the landing page and login page that assert headings, form labels, focus-visible navigation, and axe results.
- [ ] Run the new spec red if the harness cannot find the expected accessible names.
- [ ] Fix only the test/runtime wiring needed to make the real pages pass; do not redesign unrelated public UI.
- [ ] Run `npm.cmd run test:browser -- tests/Browser/public-accessibility.spec.js`.
- [ ] Commit as `test: cover public accessibility smoke paths`.

### Task 3: Cover the authenticated collaboration journey

**Files:**
- Create: `tests/Browser/collaboration.spec.js`
- Modify: `database/seeders/BrowserSeeder.php` only for deterministic fixture data.

- [ ] Add Owner assertions for login, Team, Team Work, workload metrics, axe checks, and keyboard-reachable Team Work navigation.
- [ ] Add Member assertions that the same Team Work URL returns 403 and no workload metrics.
- [ ] Run the collaboration spec red before the Team Work route exists, then run it green after the Team Work implementation is present.
- [ ] Commit as `test: verify authenticated collaboration journey`.

### Task 4: Record the release verification command

- [ ] Run `npm.cmd run test:browser` from the branch root and capture the summary.
- [ ] Run the full Pest suite and production build after browser coverage is green.
- [ ] Update the release report with exact pass/skip counts and browser/axe evidence.
- [ ] Commit the report separately as `docs: record browser accessibility evidence`.
