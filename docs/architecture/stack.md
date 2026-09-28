# PlanOps implementation baseline

> **Historical baseline notice (2026-09-05):** This document records the pre-collaboration stack boundaries. Sprint 2 supersedes the single-user boundaries for active memberships, teams, assignees, invitations, project-scoped labels, and after-commit notifications. The Laravel, PHP, PostgreSQL, Blade/Livewire, Tailwind, Vite, Pest, Playwright, and axe-core choices remain the current technical baseline unless the Sprint 2 authority says otherwise.

## Chosen baseline

PlanOps is a Laravel 13 modular monolith running PHP 8.3 with PostgreSQL. It uses normal Laravel session authentication and server-rendered Blade views enhanced with Livewire, Tailwind CSS, and Vite. The test stack is Pest/PHPUnit for application behaviour and Playwright with axe-core for browser and accessibility coverage.

The application has one relational database and keeps its domain independent of hosting or infrastructure adapters. Controllers and Livewire components stay thin; explicit Actions own mutations, Query Services compose read models and analytics, and domain Models, backed enums, rules, events, and policies retain business meaning.

## Historical v1 boundaries

- Core operations are synchronous. There are no queues or permanent queue workers.
- There is no scheduler: overdue state is computed on query, and analytics come from stored events.
- There are no WebSockets: standard HTTP mutations and navigation are sufficient for the single-user baseline.
- PostgreSQL `ILIKE` and/or built-in full text capability powers search; there is no external search service, Elasticsearch, Meilisearch, or Algolia.
- The core does not include timers, DailyPlan, recurrence, reminders, capacity, sprints, teams, assignees, comments, attachments, custom workflows, or external integrations. These are P2 or later concerns and must not reshape v1 domain truth.

## Current Sprint 2 collaboration overlay

The Sprint 2 collaboration model supersedes the historical single-user
ownership boundary for active project memberships, invitations, roles,
assignments, actor-aware history, project-scoped labels, and after-commit
notifications. Resource queries use active membership or an explicit
user-owned scope before rows are loaded; policies remain the authorization
boundary for routes and actions.

Team Work is an Owner/Admin-only, project-scoped workload view. It reports
active-member workload, blocked work, overdue work, completed-this-week counts,
and unassigned open work without productivity scores or member rankings. It
reuses the assignment and membership scopes rather than introducing a second
authorization model.

The remaining team analytics and realtime delivery remain deferred. The current
collaboration surface uses normal HTTP navigation and the database/email
notification contract; it does not claim WebSockets, realtime transport, or
productivity scoring.

## Feature priority boundaries

### P0 — Product identity

The baseline includes authentication; Projects; Tasks and Subtasks; fixed statuses and status transitions; priorities; due dates; labels; activity history; Project Board; My Work; derived project progress; Dashboard `Today`/`Week`/`Month`/`Year`; core analytics; search; and responsive, accessible controls.

### P1 — Product depth

Deferred depth includes richer saved filters, further analytics drill-down, task restoration UI, project archive experience, keyboard shortcuts, a command palette, data export, better project attention indicators, chart/table toggles, and customizable Dashboard widget ordering. P1 is deferred from the initial baseline but is not P2.

### P2 — Remaining optional expansion

Only after real demand: custom workflows, milestones, task dependencies,
attachments, comments/notes timeline, recurring tasks, reminders, GitHub/GitLab
integrations, external calendar links, AI summaries/suggestions, team analytics,
additional collaboration notifications, realtime delivery, and a public API.
These remaining P2 items are not part of the P0/P1 release gate.

## Domain module boundary

Logical modules are Identity, Projects, Tasks, Collaboration, Labels, Activity,
Dashboard, Analytics, Search, and Settings. Laravel framework concerns are
adapters around those modules, not substitutes for the contracts in
`domain-contracts.md`.

## Quality and portability contract

All reads use the applicable user-owned or active-membership scope before
serialization, including a single-user deployment. Timestamps use PostgreSQL
`TIMESTAMPTZ`; date-only deadlines use `DATE`; period boundaries are calculated
in the user's IANA timezone. The target is WCAG 2.2 AA behaviour for core
interactions, verified in browser tests with axe-core plus keyboard-path
coverage.
