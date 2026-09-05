# DYX-001 Schema and Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a PostgreSQL-compatible collaboration schema and an idempotent legacy-data migration foundation without changing membership authorization yet.

**Architecture:** Use additive migrations so legacy `projects.user_id`, `tasks.user_id`, `task_activities.user_id`, and `labels.user_id` remain readable during cutover. Add typed collaboration records and explicit identity columns first, validate existing data with a read-only preflight command, then backfill in deterministic batches and enforce database invariants. Application policies and membership-aware queries remain in DYX-002.

**Tech Stack:** Laravel 13, PHP 8.3+, Eloquent, PostgreSQL as the release database, SQLite for fast local tests, Pest/PHPUnit, and Laravel migrations/Artisan commands.

**Spec:** `docs/PlanOps_Sprint_2.md`, sections 7, 10, 11, 12, 14, 20, 21, 22, 48, and 52; execution backlog `docs/backlogs/DYX-001-schema-migration.md`.

## Global Constraints

- Preserve legacy ownership and identity columns until DYX-002 dual-read/dual-write cutover is complete.
- An active membership is a retained row with `removed_at IS NULL`; re-adding a user reactivates the same `(project_id, user_id)` row.
- Exactly one active `OWNER` membership is required per project.
- `OWNER` is never an invitation role; invitation roles are `ADMIN` and `MEMBER`.
- Invitation status is derived from `accepted_at`, `revoked_at`, and `expires_at`; do not add a status column.
- Raw invitation tokens are never stored or logged; `token_hash` stores a unique SHA-256 hex digest.
- Project keys are canonical uppercase and globally unique case-insensitively through a PostgreSQL `LOWER(key)` unique index after collision preflight.
- Task assignment is one nullable `assignee_id`; assignment validity is project membership, not a user-only foreign key.
- Project events and task activities are append-only; historical actor references use nullable `nullOnDelete` foreign keys.
- Every backfill command is read-safe in report mode, batched, resumable, idempotent, and does not expose invitation tokens.
- No DYX-002 authorization behavior is introduced in this plan.

---

### Task 1: Lock the collaboration schema contract with failing tests

**Files:**
- Modify: `tests/Feature/Database/SchemaInvariantTest.php`
- Create: `tests/Feature/Database/CollaborationSchemaTest.php`
- Create: `app/Domain/Collaboration/Enums/ProjectRole.php`
- Create: `app/Domain/Collaboration/Enums/ProjectEventType.php`

**Interfaces:**
- Produces `ProjectRole::OWNER`, `ProjectRole::ADMIN`, and `ProjectRole::MEMBER` backed by their exact string values.
- Produces the seven `ProjectEventType` values from section 10.2.
- Schema tests expose required column, index, nullability, and foreign-key names to later migration tasks.

- [ ] **Step 1: Write enum and schema assertions first.** Add tests that assert the exact enum backing values, the seven core collaboration tables, required columns, nullable fields, and named indexes. Assert PostgreSQL-only partial indexes and `jsonb`/`timestamptz` types conditionally so SQLite remains a fast feedback environment.

- [ ] **Step 2: Run the new tests and verify the red state.**

  Run: `php artisan test tests/Feature/Database/CollaborationSchemaTest.php tests/Feature/Database/SchemaInvariantTest.php --no-ansi`

  Expected: FAIL because the collaboration enums, tables, and columns do not exist.

- [ ] **Step 3: Add the enum definitions only.** Keep the enum values as string-backed PHP enums and do not add aliases or extra roles.

- [ ] **Step 4: Run the enum-focused test.**

  Run: `php artisan test --filter='ProjectRole|ProjectEventType' --no-ansi`

  Expected: PASS for enum values; schema assertions remain red.

- [ ] **Step 5: Commit the contract tests and enums.**

  ```text
  git add tests/Feature/Database/CollaborationSchemaTest.php tests/Feature/Database/SchemaInvariantTest.php app/Domain/Collaboration/Enums
  git commit -m "test: lock Sprint 2 collaboration schema invariants"
  ```

### Task 2: Add collaboration persistence tables and models

**Files:**
- Create: `database/migrations/<timestamp>_create_project_collaboration_tables.php`
- Create: `app/Domain/Collaboration/Models/ProjectMembership.php`
- Create: `app/Domain/Collaboration/Models/ProjectInvitation.php`
- Create: `app/Domain/Collaboration/Models/ProjectEvent.php`
- Create: `database/factories/ProjectMembershipFactory.php`
- Create: `database/factories/ProjectInvitationFactory.php`
- Create: `database/factories/ProjectEventFactory.php`
- Modify: `app/Domain/Projects/Models/Project.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- `ProjectMembership` exposes `project()`, `user()`, `removedBy()`, enum-cast `role`, and immutable `joined_at`, `removed_at` casts.
- `ProjectInvitation` exposes `project()`, `invitedBy()`, enum-cast `role`, derived lifecycle helpers, and no raw-token attribute.
- `ProjectEvent` exposes `project()`, `actor()`, `subject()`, enum-cast `event_type`, array-cast `metadata`, and append-only model guards.
- `Project` exposes unfiltered `memberships()`, `invitations()`, and `events()` relationships.

- [ ] **Step 1: Add migration tests for table shape.** Cover `project_memberships`, `project_invitations`, and `project_events`, including `joined_at`, `last_sent_at`, nullable actor/subject/removal fields, timestamp columns, JSON metadata, and foreign keys.

- [ ] **Step 2: Run the focused tests to confirm the schema is red.**

  Run: `php artisan test tests/Feature/Database/CollaborationSchemaTest.php --no-ansi`

  Expected: FAIL with missing-table or missing-column assertions.

- [ ] **Step 3: Implement the additive migration.** Create all three tables. Use `foreignId` references with cascade for project ownership of collaboration rows, `nullOnDelete` for historical user references, and explicit indexes for project, user, role, removal, expiry, token hash, and event chronology. Add PostgreSQL partial unique indexes through a driver-guarded raw statement for one active Owner, one pending invitation per project/email, and unique token hashes.

- [ ] **Step 4: Implement models and factories.** Add guarded/fillable fields, enum and date casts, relationships, append-only update/delete guards for `ProjectEvent`, derived invitation lifecycle methods, and factories that generate deterministic valid roles and hashed token values without exposing raw tokens.

- [ ] **Step 5: Run the schema and factory tests.**

  Run: `php artisan test tests/Feature/Database/CollaborationSchemaTest.php --no-ansi`

  Expected: PASS on SQLite-compatible assertions; PostgreSQL CI must additionally pass partial-index and type assertions.

- [ ] **Step 6: Commit the persistence layer.**

  ```text
  git add database/migrations app/Domain/Collaboration app/Domain/Projects/Models/Project.php app/Models/User.php database/factories
  git commit -m "feat: add collaboration persistence tables"
  ```

### Task 3: Add additive identity and assignment columns

**Files:**
- Create: `database/migrations/<timestamp>_add_collaboration_identity_fields.php`
- Modify: `app/Domain/Projects/Models/Project.php`
- Modify: `app/Domain/Tasks/Models/Task.php`
- Modify: `app/Domain/Activity/Models/TaskActivity.php`
- Modify: `app/Domain/Labels/Models/Label.php`
- Modify: `app/Models/User.php`
- Modify: `tests/Feature/Database/CollaborationSchemaTest.php`

**Interfaces:**
- Projects gain nullable `owner_id` while legacy `user_id` remains intact.
- Tasks gain nullable `created_by_user_id` and `assignee_id` plus the `(project_id, assignee_id, status)` lookup index.
- Task activities gain nullable `actor_user_id` with historical `nullOnDelete` behavior.
- Users gain nullable `deactivated_at`.
- Labels gain the additive project association needed for project-scoped uniqueness without removing legacy user ownership in this migration.

- [ ] **Step 1: Extend schema tests before migration changes.** Assert every new field’s nullability, type, index, and FK behavior; assert legacy columns remain present.

- [ ] **Step 2: Run the tests and confirm the missing-field failures.**

  Run: `php artisan test tests/Feature/Database/CollaborationSchemaTest.php --no-ansi`

  Expected: FAIL only for the new fields and indexes.

- [ ] **Step 3: Add the additive migration.** Add nullable columns and safe foreign keys. Do not make `owner_id` or `created_by_user_id` required until the backfill validation task succeeds. Add nullable `labels.project_id`; infer it from `task_label` attachments during preflight/backfill, duplicate labels when one legacy label spans projects, retain unattached labels as private legacy rows, and enforce `(project_id, normalized_name)` uniqueness only after the backfill report is accepted.

- [ ] **Step 4: Update model casts, relationships, fillable lists, and factories.** Preserve existing owner-based accessors for compatibility and add explicit `owner`, `creator`, `assignee`, and `actor` relationships without changing query scopes yet.

- [ ] **Step 5: Verify fresh migrations and legacy columns.**

  Run: `php artisan migrate:fresh --seed; php artisan test tests/Feature/Database/CollaborationSchemaTest.php --no-ansi`

  Expected: PASS for the additive schema and seeded legacy tables remain readable.

- [ ] **Step 6: Commit the identity-field migration.**

  ```text
  git add database/migrations app/Domain/Projects/Models/Project.php app/Domain/Tasks/Models/Task.php app/Domain/Activity/Models/TaskActivity.php app/Domain/Labels/Models/Label.php app/Models/User.php tests/Feature/Database/CollaborationSchemaTest.php
  git commit -m "feat: add collaboration identity fields"
  ```

### Task 4: Build the read-only migration preflight report

**Files:**
- Create: `app/Console/Commands/PlanOpsCollaborationPreflight.php`
- Create: `tests/Feature/Database/CollaborationPreflightTest.php`
- Create: `docs/baselines/2026-09-05-dyx-001-preflight.md`

**Interfaces:**
- Command: `php artisan planops:collaboration-preflight --json` returns stable counts and bounded sample IDs.
- Report categories: missing owners, case-insensitive duplicate project keys, duplicate task numbers, orphaned task/activity/label references, duplicate normalized labels, invalid assignees, and existing activity rows.
- JSON output never contains raw invitation tokens or full personal-data dumps.

- [ ] **Step 1: Write tests for clean and anomalous fixtures.** Assert zero counts on a valid seeded database, detection of each anomaly on a deliberately constructed fixture, stable sorted sample IDs, and unchanged row counts before and after execution.

- [ ] **Step 2: Run the tests and verify the command is missing.**

  Run: `php artisan test tests/Feature/Database/CollaborationPreflightTest.php --no-ansi`

  Expected: FAIL because the command and report contract do not exist.

- [ ] **Step 3: Implement read-only queries.** Use aggregates and bounded ordered samples, make the output schema explicit, and avoid writes, model events, token values, and non-deterministic unordered samples.

- [ ] **Step 4: Verify repeatability.** Run the command twice against the same database and compare normalized JSON output and row counts.

- [ ] **Step 5: Save the production-shaped report.** Record the command, database target, date, counts, and anomaly decisions in the dated report; do not claim PostgreSQL validation until a PostgreSQL run exists.

- [ ] **Step 6: Commit the preflight report.**

  ```text
  git add app/Console/Commands/PlanOpsCollaborationPreflight.php tests/Feature/Database/CollaborationPreflightTest.php docs/baselines/2026-09-05-dyx-001-preflight.md
  git commit -m "feat: add collaboration migration preflight"
  ```

### Task 5: Implement idempotent, batched legacy backfill

**Files:**
- Create: `app/Console/Commands/PlanOpsCollaborationBackfill.php`
- Create: `tests/Feature/Database/CollaborationBackfillTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `database/factories/ProjectFactory.php`
- Modify: `database/factories/TaskFactory.php`
- Modify: `database/factories/TaskActivityFactory.php`

**Interfaces:**
- Command: `php artisan planops:collaboration-backfill --chunk=500 --no-interaction`.
- `--dry-run` reports planned changes without writing.
- Re-running the command does not duplicate memberships, change task numbers, or create duplicate events.
- Backfill rules: `projects.owner_id = projects.user_id`; one retained active Owner membership per project; `tasks.created_by_user_id = tasks.user_id`; safe legacy assignee mapping is explicit and otherwise null; `task_activities.actor_user_id = task_activities.user_id`; canonical project keys are trimmed and uppercased only after collision decisions are recorded.

- [ ] **Step 1: Write migration tests for fresh, legacy, repeated, and anomalous data.** Assert counts, owner/membership agreement, preserved task keys and activities, null-safe assignees, and no changes under `--dry-run`.

- [ ] **Step 2: Run the tests and verify the backfill fails before implementation.**

  Run: `php artisan test tests/Feature/Database/CollaborationBackfillTest.php --no-ansi`

  Expected: FAIL because the backfill command is not registered.

- [ ] **Step 3: Implement bounded transactions.** Process projects, tasks, and activities with stable primary-key cursors. Upsert memberships by `(project_id, user_id)`, lock only the current project row when needed, and preserve existing values on rerun. Refuse to canonicalize or enforce global keys when preflight reports unresolved collisions.

- [ ] **Step 4: Make factories and seeders create valid collaboration states.** Seed at least one Owner, Admin, and Member scenario, plus assigned and unassigned tasks and actor history, while keeping the seeded snapshot deterministic.

- [ ] **Step 5: Run fresh and repeated backfill tests.**

  Run: `php artisan test tests/Feature/Database/CollaborationBackfillTest.php tests/Feature/Database/SeedReproducibilityTest.php --no-ansi`

  Expected: PASS with identical snapshots and exactly one active Owner per project.

- [ ] **Step 6: Commit the backfill implementation.**

  ```text
  git add app/Console/Commands/PlanOpsCollaborationBackfill.php tests/Feature/Database/CollaborationBackfillTest.php database/seeders/DatabaseSeeder.php database/factories
  git commit -m "feat: add idempotent collaboration backfill"
  ```

### Task 6: Verify database invariants and release readiness

**Files:**
- Modify: `tests/Feature/Database/CollaborationSchemaTest.php`
- Modify: `tests/Feature/Database/CollaborationBackfillTest.php`
- Create: `tests/Feature/Database/CollaborationConstraintTest.php`
- Create: `docs/reports/2026-09-05-dyx-001-migration-verification.md`
- Modify: `docs/backlogs/DYX-001-schema-migration.md`

**Interfaces:**
- Constraint tests prove duplicate active Owners, duplicate pending invitations, duplicate token hashes, invalid references, and append-only event mutation are rejected.
- The verification report records database engine/version, commands, exit codes, counts, and unresolved anomalies.

- [ ] **Step 1: Write constraint and rollback-rehearsal tests.** Use transactions and real database constraints; skip only PostgreSQL-specific assertions when the local driver is SQLite, and mark those checks mandatory in the PostgreSQL command list.

- [ ] **Step 2: Run the focused verification suite and confirm any missing constraints.**

  Run: `php artisan test tests/Feature/Database/CollaborationSchemaTest.php tests/Feature/Database/CollaborationPreflightTest.php tests/Feature/Database/CollaborationBackfillTest.php tests/Feature/Database/CollaborationConstraintTest.php --no-ansi`

  Expected: PASS on supported local assertions; PostgreSQL-only checks must be run separately before acceptance.

- [ ] **Step 3: Run the PostgreSQL verification commands.**

  ```text
  php artisan migrate:fresh --seed
  php artisan planops:collaboration-preflight --json
  php artisan planops:collaboration-backfill --dry-run --no-interaction
  php artisan planops:collaboration-backfill --chunk=500 --no-interaction
  php artisan planops:collaboration-backfill --chunk=500 --no-interaction
  php artisan test --filter='Schema|Migration|Collaboration' --no-ansi
  ```

- [ ] **Step 4: Compare row counts, keys, owner invariants, and second-run output.** Record every unresolved anomaly with an owner and release treatment; do not mark DYX-001 accepted if the database engine or required invariant is unverified.

- [ ] **Step 5: Update the backlog acceptance checklist and report.** Mark only criteria backed by command output, link the report, and leave DYX-002 blocked until membership-aware policies can consume the new records safely.

- [ ] **Step 6: Commit the verification evidence.**

  ```text
  git add tests/Feature/Database docs/reports/2026-09-05-dyx-001-migration-verification.md docs/backlogs/DYX-001-schema-migration.md
  git commit -m "test: verify collaboration migration invariants"
  ```

## Plan Self-Review

- Coverage: tables, typed values, identity fields, preflight anomalies, batched backfill, idempotency, constraints, factories, PostgreSQL verification, and release documentation each have an explicit task.
- Scope: authorization policies, invitation HTTP flows, assignment actions, notifications, and collaboration UI are intentionally deferred to DYX-002 through DYX-006.
- Safety: legacy columns remain until cutover; raw invitation tokens never enter reports; unresolved key collisions stop enforcement rather than being silently renamed.
- Verification: SQLite provides fast structural feedback, but PostgreSQL partial indexes, JSONB, timestamp types, and concurrency checks remain mandatory before DYX-001 acceptance.
