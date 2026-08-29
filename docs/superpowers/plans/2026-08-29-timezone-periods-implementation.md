# Timezone-aware Report Periods Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Provide immutable local-calendar periods converted to safe UTC query boundaries.

**Architecture:** `ReportPeriod` carries the display label, UTC start/end, and chart bucket. `UserPeriodResolver` computes local boundaries from the user’s IANA timezone and week-start preference, then converts them to UTC. `ReportPeriodRequest` validates standard/custom period input at the HTTP edge.

**Tech Stack:** Laravel 13, CarbonImmutable, Pest, existing UserPreference model.

**Spec:** DYX-015 in `docs/superpowers/plans/2026-08-20-planops-implementation.md`.

## Global Constraints

- Use IANA timezone strings from `UserPreference`.
- Use half-open UTC intervals `[start, end)`.
- Custom end dates are inclusive in the user’s local calendar.
- Supported week starts are `MONDAY` and `SUNDAY`.
- Bucket values are only `day`, `week`, or `month`.
- Reject reversed custom ranges.

### Task 1: Define period value object and resolver

- [ ] Write failing unit tests for Casablanca day, Monday/Sunday week, month/year, custom inclusivity, and reversed ranges.
- [ ] Run the focused unit test and record PHP availability.
- [ ] Implement `ReportPeriod` and `UserPeriodResolver`.
- [ ] Run focused tests and commit `feat: add timezone-aware report periods`.

### Task 2: Add HTTP period validation

- [ ] Write failing feature tests for standard period selection and invalid custom ranges.
- [ ] Implement `ReportPeriodRequest` with safe defaults and validated values.
- [ ] Run settings period tests and commit `test: validate report period input`.

### Final verification

- [ ] Run focused tests, `npm run build`, and `git diff --check`.
- [ ] Confirm clean `main` and report PHP limitations honestly.
