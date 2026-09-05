# DYX-001 migration verification — 2026-09-05

## Local verification

Environment: PHP 8.3.33, Laravel 13, SQLite test database.

| Command | Exit | Result |
| --- | ---: | --- |
| `php artisan migrate:fresh --seed --no-interaction` | 0 | All migrations and deterministic seed completed. |
| `php artisan planops:collaboration-backfill --dry-run --chunk=500 --no-interaction` | 0 | 5 projects and 5 memberships planned; no writes. |
| `php artisan planops:collaboration-backfill --chunk=500 --no-interaction` | 0 | 5 projects, 5 memberships, 7 tasks, 3 activities, and 2 labels backfilled. |
| Same backfill command a second time | 0 | Zero rows changed; rerun is idempotent. |
| Focused schema/preflight/backfill/constraint tests | 0 | 12 tests passed, 95 assertions. |

The backfill preserves legacy columns, canonicalizes project keys to uppercase, creates one active Owner membership per project, copies task creator/assignee and activity actor identities, and infers project-scoped labels from task attachments.

## Remaining release verification

- PostgreSQL still must verify partial unique indexes for one active Owner and one pending invitation per project/email.
- PostgreSQL still must verify JSONB/timestamptz column types and concurrent constraint behavior.
- A production-shaped snapshot must be run through the preflight command before any live backfill.
- DYX-001 remains in progress until those PostgreSQL checks and anomaly decisions are recorded.
