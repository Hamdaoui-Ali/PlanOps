# DYX-001 collaboration preflight — 2026-09-05

**Command:** `php artisan planops:collaboration-preflight --json`

**Database:** Fresh local SQLite migration with the current deterministic seed. PostgreSQL verification is still required for partial-index, JSONB, timestamp, and concurrency behavior.

**Result:** Exit code `0`; report mode performed no writes.

```json
{
  "missing_project_owners": {"count": 5, "sample_ids": [1, 2, 3, 4, 5]},
  "duplicate_project_keys": {"count": 0, "sample_ids": []},
  "duplicate_task_numbers": {"count": 0, "sample_ids": []},
  "orphaned_tasks": {"count": 0, "sample_ids": []},
  "orphaned_activities": {"count": 0, "sample_ids": []},
  "orphaned_labels": {"count": 0, "sample_ids": []},
  "duplicate_project_labels": {"count": 0, "sample_ids": []},
  "legacy_labels_spanning_projects": {"count": 0, "sample_ids": []},
  "invalid_assignees": {"count": 0, "sample_ids": []},
  "existing_activity_rows": {"count": 3, "sample_ids": [1, 3, 4]}
}
```

## Decisions

- The five missing `owner_id` values are expected pre-backfill anomalies and will be populated from the legacy `projects.user_id` column.
- Three existing activity task IDs require historical `actor_user_id` backfill from `task_activities.user_id`.
- No duplicate project keys, duplicate task numbers, orphaned references, cross-project legacy labels, duplicate project labels, or invalid assignees were found in the seeded database.
- The backfill remains blocked from production use until a PostgreSQL-shaped snapshot is reported and key-collision resolution is reviewed.

## Repeatability check

The command is covered by `tests/Feature/Database/CollaborationPreflightTest.php`, including a no-write anomaly fixture and stable sample ordering.
