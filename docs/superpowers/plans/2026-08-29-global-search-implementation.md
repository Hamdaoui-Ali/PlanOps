# Global Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a keyboard-friendly, owner-scoped global search for projects and tasks.

**Architecture:** Use one parameterized Eloquent query service with explicit result limits and ordering. Keep the HTTP boundary thin, render separate project/task result groups, and expose a compact search form from the authenticated navigation.

**Tech Stack:** Laravel 13, Eloquent/PostgreSQL, Blade, Alpine.js, Pest, Vite.

**Spec:** DYX-014 in `docs/superpowers/plans/2026-08-20-planops-implementation.md`.

## Global Constraints

- Search is scoped by `user_id` before matching.
- Soft-deleted tasks are excluded.
- Results are capped at 20 tasks and 20 projects per search.
- Matching covers task key/title/description/project name/labels and project name/key.
- Search uses parameterized `ILIKE`-style matching and no external search service.
- Empty and short input show an actionable state; results are keyboard-navigable links.

### Task 1: Add the search service and request contract

**Files:** `app/Domain/Search/Queries/SearchQueryService.php`, `app/Http/Requests/SearchRequest.php`, `tests/Feature/Search/SearchQueryTest.php`.

- [ ] Write failing tests for key/title/description/project/label matches, ownership, deletion, caps, and empty input.
- [ ] Run the focused test and record the expected PHP-unavailable result if applicable.
- [ ] Implement `SearchQueryService::search(User $user, string $term): array` with `tasks` and `projects` collections.
- [ ] Validate a trimmed `q` string and cap each result type at 20.
- [ ] Run diff checks and commit `feat: add global search query service`.

### Task 2: Add the route and result UI

**Files:** `app/Http/Controllers/SearchController.php`, `routes/web.php`, `resources/views/pages/search/index.blade.php`, `resources/views/components/search/result-list.blade.php`.

- [ ] Add failing route assertions for result labels, links, empty state, and short terms.
- [ ] Implement the controller and authenticated `search` route.
- [ ] Render grouped result lists with visible text and no-results guidance.
- [ ] Commit `feat: add global search screen`.

### Task 3: Add global keyboard access and final verification

**Files:** `resources/views/layouts/navigation.blade.php`, `resources/js/app.js`, `resources/css/app.css`, `tests/Feature/Search/SearchQueryTest.php`.

- [ ] Add a labeled search control to the navigation with `/` focus shortcut and Escape behavior.
- [ ] Add responsive styles and preserve visible focus.
- [ ] Run the relevant Laravel tests, `npm run build`, `git diff --check`, and confirm clean `main`.
- [ ] Commit `test: verify global search interactions`.

## Final verification

- [ ] Report PHP/Pest availability honestly.
- [ ] Do not push unless explicitly requested.
