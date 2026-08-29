# Fact-based Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add global and project analytics derived from owned task lifecycle facts and activity events.

**Architecture:** `AnalyticsQueryService` reads the selected `ReportPeriod`, current task fields, and append-only activities. It returns an immutable `AnalyticsSnapshot` containing throughput, median durations, time-in-status, and project contribution; controllers only select the global or project scope.

**Tech Stack:** Laravel 13, Eloquent, CarbonImmutable, Blade, Pest, Vite.

**Spec:** DYX-017 in `docs/superpowers/plans/2026-08-20-planops-implementation.md`.

## Global Constraints

- Use recorded facts; never infer productivity from incomplete data.
- Scope every task, project, and activity query to the authenticated owner.
- Use first completion in period for lead/cycle summaries; keep all activity visible.
- Use median elapsed calendar time, not averages.
- Cap intervals to the selected UTC period.
- Show explicit insufficient-data states.
- Provide numeric tables alongside visual summaries.

### Task 1: Define analytics snapshot and calculations

- [ ] Write failing tests for throughput, first completion, medians, time-in-status, ownership, and project contribution.
- [ ] Run focused tests and record PHP availability.
- [ ] Implement `AnalyticsSnapshot` and `AnalyticsQueryService` for global/project scope.
- [ ] Commit `feat: add fact-based analytics service`.

### Task 2: Add global and project analytics screens

- [ ] Write failing route/controller screen assertions.
- [ ] Implement `AnalyticsController` and `ProjectAnalyticsController` with safe period selection.
- [ ] Render accessible metric cards and numeric tables with no-data copy.
- [ ] Commit `feat: add analytics screens`.

### Final verification

- [ ] Run analytics tests, `npm run build`, and `git diff --check`.
- [ ] Confirm clean `main`; report unavailable PHP/browser checks honestly.
