# Global Activity Feed Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the temporary Activity page with an owner-scoped, filterable and paginated activity timeline.

**Architecture:** Reuse `TaskActivityFeedQuery` for the global feed and its existing task timeline. Add a validated request and thin controller, then render a display-ready Blade timeline that formats event values without exposing raw JSON.

**Tech Stack:** Laravel 13, Eloquent, Blade, Pest, existing PlanOps CSS, Alpine-free progressive HTML.

**Spec:** `docs/superpowers/plans/2026-08-20-planops-implementation.md` DYX-010 and the approved activity-feed design from this session.

## Global Constraints

- Every activity result is scoped to the authenticated owner.
- Global activity is newest-first and paginated at 50 rows.
- Task timelines remain oldest-first.
- Deleted tasks remain available as historical context through `withTrashed`.
- Filters are project, task, event type, and UTC-converted period bounds.
- Raw JSON is never the primary UI representation.
- Empty states use concise actionable text.

### Task 1: Add validated activity filters and controller boundary

**Files:**
- Create: `app/Http/Requests/ActivityFiltersRequest.php`
- Create: `app/Http/Controllers/ActivityController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Activity/GlobalActivityFeedTest.php`

**Interfaces:**
- `ActivityFiltersRequest::filters(): array` returns safe `project_id`, `task_id`, `event_type`, `from`, and `until` values.
- `ActivityController::index(ActivityFiltersRequest $request, TaskActivityFeedQuery $feed): View` renders `pages.activity.index`.

- [ ] Write tests for owner scoping, filter validation, route success, and empty state.
- [ ] Run `php artisan test tests/Feature/Activity/GlobalActivityFeedTest.php`; record the PHP-unavailable result if applicable.
- [ ] Implement request validation, controller data loading, and the authenticated `/activity` route.
- [ ] Run `git diff --check` and commit `feat: add global activity feed boundary`.

### Task 2: Render accessible activity timeline

**Files:**
- Create: `resources/views/pages/activity/index.blade.php`
- Create: `resources/views/components/activity/timeline.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/views/components/navigation/sidebar.blade.php`

**Interfaces:**
- The page receives `activities`, `filters`, `projects`, `tasks`, and `eventTypes`.
- The timeline receives a paginator and renders key, task title, project, event label, safe field values, and local timestamp.

- [ ] Add the failing view assertions for readable event text, filter controls, pagination, and no raw JSON.
- [ ] Implement the timeline with semantic `ol`/`li`, visible labels, and reset/apply controls.
- [ ] Add responsive styles using the existing PlanOps visual language.
- [ ] Run `npm run build` and commit `feat: render global activity timeline`.

### Task 3: Complete verification and regression coverage

**Files:**
- Modify: `tests/Feature/Activity/GlobalActivityFeedTest.php`
- Modify: `tests/Feature/Activity/TaskTimelineTest.php` if needed

- [ ] Cover event-type/date/project/task filter combinations and pagination size 50.
- [ ] Cover deleted task context and foreign-user exclusion.
- [ ] Run `php artisan test tests/Feature/Activity tests/Feature/Tasks/TaskDetailTest.php`.
- [ ] Run `npm run build`, `git diff --check`, and confirm clean `main`.
- [ ] Commit `test: verify global activity feed`.

## Final verification

- [ ] `git status --short --branch` is clean on `main`.
- [ ] PHP/Pest results are reported honestly if PHP remains unavailable.
- [ ] Do not push unless explicitly requested.
