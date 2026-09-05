# DYX-001 — Schema, invariants, and migration

**Status:** In progress — local schema/backfill verified; PostgreSQL release checks remain

**Priority:** P0

**Dependency:** [DYX-000](DYX-000-contract-baseline.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 7, 10, 11, 12, 14, 20, 21, 22, 48, and 52.

## Goal

Add the collaboration schema and migrate existing PlanOps data without losing access, task history, project keys, or referential integrity.

## Files

- `database/migrations/`
- `app/Domain/Collaboration/Enums/ProjectRole.php`
- `app/Domain/Collaboration/Enums/ProjectEventType.php`
- `app/Domain/Collaboration/Models/ProjectMembership.php`
- `app/Domain/Collaboration/Models/ProjectInvitation.php`
- `app/Domain/Collaboration/Models/ProjectEvent.php`
- `app/Domain/Projects/Models/Project.php`
- `app/Domain/Tasks/Models/Task.php`
- `app/Domain/Activity/Models/TaskActivity.php`
- `app/Domain/Labels/Models/Label.php`
- `app/Models/User.php`
- `database/factories/`
- `database/seeders/`
- `tests/Feature/Database/`
- migration and release reports under `docs/`

## Tasks

### Task DYX-001.1

Goal: Add the typed collaboration records and lifecycle fields.

Files: collaboration enums/models/migrations and the existing project, task, activity, label, and user migrations.

Action: Add `project_memberships` with `project_id`, `user_id`, `role`, `removed_at`, `removed_by_user_id`, timestamps, and the documented indexes/FKs. Add `project_invitations` with `project_id`, `invited_by_user_id`, `normalized_email`, `role`, `token_hash`, `expires_at`, `accepted_at`, `revoked_at`, and timestamps. Add immutable `project_events` with the exact event values from the authority document.

Why: authorization and lifecycle actions need durable records rather than owner-only conventions.

Verification: Schema tests assert exact columns, enum values, indexes, nullability, foreign keys, and timestamp behavior on PostgreSQL.

Expected result: The database can represent active/removed membership, invitation state, ownership changes, and project security history.

### Task DYX-001.2

Goal: Add creator, assignee, actor, and account-lifecycle fields.

Files: project/task/activity/user migrations and corresponding models/factories.

Action: Add `projects.owner_id`, `tasks.created_by_user_id`, nullable `tasks.assignee_id`, activity `actor_user_id`, and `users.deactivated_at`. Preserve legacy `user_id` fields until cutover and define the relationship/FK behavior explicitly.

Why: creator, assignee, and actor are different identities and must not be collapsed into one owner field.

Verification: Model and schema tests prove that existing rows remain readable, assignees are nullable, actor history is retained, and account deactivation does not erase required project history.

Expected result: The data model can distinguish who owns a project, created a task, is assigned the task, and performed each mutation.

### Task DYX-001.3

Goal: Produce a migration preflight report before changing live data.

Files: migration command/script, report output, and migration tests.

Action: Report duplicate project keys under `LOWER(key)`, missing owners, orphaned project/task/activity references, duplicate normalized labels, unattached legacy labels, invalid assignees, and existing activity rows. Use explicit counts and sample identifiers without exposing invitation tokens.

Why: irreversible or partially applied backfills must be based on observed data, not assumptions from factories.

Verification: Run the report against a production-shaped PostgreSQL snapshot and assert that rerunning it is read-only and produces stable counts.

Expected result: A migration decision is documented for every detected anomaly before the write phase.

### Task DYX-001.4

Goal: Backfill data in an idempotent, rollback-aware sequence.

Files: additive migrations, backfill command/job, rollback notes, and migration tests.

Action: Add owner memberships for existing project owners; backfill `owner_id`, `created_by_user_id`, actor fields, and safe assignees in batches. Canonicalize project keys to uppercase while enforcing a global case-insensitive uniqueness rule with `LOWER(key)`. Keep legacy columns readable until cutover and make a second run a no-op.

Why: existing personal projects must remain accessible as Owner projects and a failed batch must be resumable.

Verification: Run fresh migrations, legacy-data migrations, a repeated backfill, and a rollback rehearsal. Compare project/task/activity counts and assert exactly one active `OWNER` membership per project.

Expected result: Existing data is migrated without duplicate owners, orphaned references, duplicate global keys, or task-history loss.

### Task DYX-001.5

Goal: Enforce database-level invariants that application code depends on.

Files: constraint/index migrations and schema tests.

Action: Add the PostgreSQL partial unique index for exactly one active Owner, active-membership lookup indexes, pending-invitation uniqueness on `(project_id, normalized_email)`, unique token hash, project-scoped label uniqueness on `(project_id, normalized_name)`, and the documented FK/delete behavior.

Why: concurrent requests can bypass application-only checks.

Verification: Attempt duplicate active Owners, duplicate pending invitations, duplicate project-scoped labels, invalid assignees, and cross-project references in transaction tests. Assert each fails safely.

Expected result: The database rejects invalid collaboration states under concurrent or repeated writes.

### Task DYX-001.6

Goal: Make fixtures and seed data reflect the new invariants.

Files: `database/factories/`, `database/seeders/`, and schema/domain tests.

Action: Add deterministic factories for memberships, invitations, project events, assigned/unassigned tasks, and actor history. Seed at least one Owner, Admin, and Member scenario without creating invalid duplicate Owners.

Why: tests and local development must exercise collaboration states intentionally.

Verification: Run the relevant factory tests and seed a fresh database twice; both runs must satisfy the same invariants.

Expected result: Later policy and lifecycle tests can create valid collaboration fixtures without hidden setup logic.

## Acceptance criteria

- [ ] All required collaboration tables, fields, enums, indexes, and FKs exist on PostgreSQL.
- [ ] Existing projects receive exactly one active `OWNER` membership.
- [ ] `projects.owner_id` agrees with that active Owner membership.
- [ ] Backfill is batched, resumable, idempotent, and verified on a second run.
- [ ] Project keys are globally unique case-insensitively through `LOWER(key)` and canonical uppercase writes.
- [ ] Pending invitations are unique per project/email and token hashes are unique without logging raw tokens.
- [ ] Labels are unique by project and normalized name after migration planning is complete.
- [ ] Existing project/task/activity counts and task keys remain stable.
- [ ] Fresh and legacy migration tests pass before DYX-002 starts.

## Current evidence

- [x] Collaboration tables, enums, additive identity fields, and local schema tests pass.
- [x] Read-only preflight report is recorded in [the dated baseline](../baselines/2026-09-05-dyx-001-preflight.md).
- [x] Local dry-run, backfill, and repeated-backfill checks are recorded in [the migration verification report](../reports/2026-09-05-dyx-001-migration-verification.md).
- [ ] PostgreSQL partial-index, type, and concurrency verification is complete.
- [ ] Production-shaped preflight anomalies are reviewed and approved.

## Verification commands

```text
php artisan migrate:fresh --seed
php artisan test --filter=Schema
php artisan test --filter=Migration
php artisan test --filter=Collaboration
```

The migration suite must also run against PostgreSQL; SQLite-only success is insufficient for partial indexes, JSONB, timestamps, and concurrency invariants.

## Expected result

A PostgreSQL-compatible, repeatable collaboration schema with an accepted preflight report and no loss of existing PlanOps data.

## Suggested commit boundaries

- `test: lock Sprint 2 collaboration schema invariants`
- `feat: add collaboration persistence and idempotent backfill`
- `test: verify collaboration migration and concurrency invariants`

## Next action

Review [the preflight report](../baselines/2026-09-05-dyx-001-preflight.md), then implement the idempotent backfill and PostgreSQL constraint verification.
