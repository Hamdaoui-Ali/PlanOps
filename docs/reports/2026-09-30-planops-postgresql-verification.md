# PlanOps PostgreSQL verification

**Date:** 2026-09-30

**Branch:** `feature/planops-release-gate-contracts`

**Purpose:** Record the final local SQLite and PostgreSQL application-suite evidence for DYX-007 after stabilizing the database-specific test harness.

## Environment

- PostgreSQL 18 was initialized as a fresh disposable cluster for the run.
- The cluster listened on `127.0.0.1:55432` and was removed after the test process exited.
- The run used Laravel's normal migration bootstrap and did not change `.env` or tracked database data.
- PostgreSQL connections now use `DB_TIMEZONE`, defaulting to `UTC`, so `timestampTz` values are bound and read consistently across hosts.
- The PostgreSQL run was executed from PowerShell on Windows. The disposable cluster used local/host trust authentication only for this verification run.

## Final results

| Database | Command | Result | Duration |
| --- | --- | --- | --- |
| SQLite | `php artisan test --no-ansi` | 366 passed, 3 environment-scoped skips, 1,736 assertions | 14.06s |
| PostgreSQL 18 | `php artisan test --no-ansi` with `DB_CONNECTION=pgsql` and the disposable cluster | 368 passed, 1 environment-scoped skip, 1,768 assertions | 65.22s |

SQLite's three skips are the existing PostgreSQL-only independent-process checks in `AssignmentConcurrencyTest`, `TaskCreationConcurrencyTest`, and `TaskNumberConcurrencyTest`.

The PostgreSQL run passed the assignment and task-creation race checks. Its single skip is the independent task-number allocation race because the Windows PHP runtime does not provide the required POSIX process-control extensions (`pcntl_fork`, `pcntl_waitpid`, and `posix_kill`).

## Harness and contract fixes

- PostgreSQL 18 schema metadata is accepted when it reports `timestamp(0) with time zone` instead of the short `timestamptz` label.
- Seed reproducibility resets PostgreSQL identities and establishes a clean first baseline before comparing fixture snapshots.
- Intentional duplicate-key assertions run inside savepoints so the PostgreSQL transaction remains usable for the rest of the test.
- Independent race workers use explicit named connections and inherit the complete process environment on Windows.
- PostgreSQL timestamp sessions are pinned to UTC through `config/database.php`; a schema invariant test protects that contract.
- JSONB object assertions compare canonical key/value content instead of relying on PostgreSQL's object-key order.

## Remaining release boundary

This is strong local application-suite evidence on SQLite and PostgreSQL 18. It does not yet prove the production migration/rollback procedure, a legacy-data migration rehearsal against production-like data, or a CI run with the target deployment services. Those remain explicit DYX-007 release-gate work rather than being inferred from this report.

Related evidence:

- [DYX-007 release verification checklist](../backlogs/DYX-007-release-verification.md)
- [account lifecycle and security-scope audit](2026-09-30-planops-account-lifecycle-scope-audit.md)
- [collaboration browser verification](2026-09-30-planops-collaboration-browser-verification.md)
- [notification recipient suppression verification](2026-09-30-planops-notification-recipient-suppression-verification.md)
