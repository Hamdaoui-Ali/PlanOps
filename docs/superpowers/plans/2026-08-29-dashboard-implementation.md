# Period-aware Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the temporary dashboard with current-state KPIs and period-aware tracked-work summaries.

**Architecture:** `DashboardQueryService` composes owner-scoped task/activity queries into a `DashboardSnapshot`. Current KPIs never change with the selected reporting period; period metrics use `ReportPeriod` bounds and recorded events. Blade components render both visual summaries and accessible numeric tables.

**Tech Stack:** Laravel 13, Eloquent, CarbonImmutable, Blade, Pest, Vite.

**Spec:** DYX-016 in `docs/superpowers/plans/2026-08-20-planops-implementation.md`.

## Global Constraints

- Current-state KPIs ignore the selected period.
- Period metrics use distinct top-level tasks and recorded activity events.
- Soft-deleted tasks are excluded from current and period metrics.
- Cancelled tasks are hidden from the main status distribution.
- Reopen and no-data semantics remain explicit.
- Dashboard output is owner-scoped and timezone-aware through `ReportPeriod`.
- Numeric summaries accompany chart-like displays.

### Task 1: Define snapshot and metric query service

- [ ] Write failing unit/feature tests for current KPIs, period counts, ownership, cancelled tasks, and period switching.
- [ ] Run focused tests and record PHP availability.
- [ ] Implement `DashboardSnapshot` and `DashboardQueryService::for(User $user, ReportPeriod $period): DashboardSnapshot`.
- [ ] Verify formulas and commit `feat: add dashboard metric contracts`.

### Task 2: Add period selector and dashboard route

- [ ] Write failing request/controller tests for today/week/month/year/custom selection.
- [ ] Implement `DashboardPeriodRequest` and inject `UserPeriodResolver` in `DashboardController`.
- [ ] Keep `/dashboard` authenticated and preserve safe query parameters.
- [ ] Commit `feat: add dashboard period selection`.

### Task 3: Build accessible dashboard UI

- [ ] Add failing screen assertions for exact labels, no-data copy, tables, and status sections.
- [ ] Implement dashboard Blade components and responsive CSS using the existing console language.
- [ ] Run `npm run build` and commit `feat: render period-aware dashboard`.

### Final verification

- [ ] Run dashboard/related tests, build, and `git diff --check`.
- [ ] Confirm clean `main`; report PHP/browser limitations honestly.
- [ ] Do not push unless explicitly requested.
