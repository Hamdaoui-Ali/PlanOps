# DYX-003 Task 2 Report: Persistence Tables and Backed Enums

## Status

Implementation committed. Runtime migration and test verification is blocked because PHP is unavailable on PATH.

## Commit

- `2c8511f feat: add PlanOps domain schema and enums`

## Scope Delivered

- Modified the existing `2026_08_21_000000_create_user_preferences_table.php` migration in place: `timestamps()` is now `timestampsTz()`; its `user_id` uniqueness, cascading user FK, and four existing defaults remain unchanged.
- Added PostgreSQL-first / SQLite-test-compatible migrations for `projects`, `tasks`, `labels`, `task_label`, and `task_activities` using the exact requested columns, defaults, timestamp types, relationship delete behavior, unique keys, and lookup indexes.
- Added string-backed domain enums:
  - `App\\Domain\\Projects\\Enums\\ProjectStatus`
  - `App\\Domain\\Tasks\\Enums\\TaskStatus`
  - `App\\Domain\\Tasks\\Enums\\TaskPriority`
  - `App\\Domain\\Activity\\Enums\\TaskActivityType`
  - `App\\Domain\\Identity\\Enums\\ThemePreference`
  - `App\\Domain\\Identity\\Enums\\DensityPreference`
  - `App\\Domain\\Identity\\Enums\\WeekStartDay`
- Implemented `TaskStatus::category()` exactly with the required planned, active, and terminal mappings.
- No HTTP, Action, model, factory, or ownership-test work was added.

## TDD Evidence

Task 1 supplied the focused acceptance tests before this implementation:

- `tests/Feature/Database/SchemaInvariantTest.php`
- `tests/Unit/Domain/Tasks/TaskStatusTest.php`

The required red/green test execution could not start because the PHP executable is not available. The required commands were attempted both before and after the source changes. Therefore no test assertions executed, and a true RED or GREEN result is unavailable.

### RED command attempt (before implementation)

```text
php artisan migrate:fresh
```

Exit code: `1`

```text
php : Le terme «php» n'est pas reconnu comme nom d'applet de commande, fonction, fichier de script ou programme
exécutable. Vérifiez l'orthographe du nom, ou si un chemin d'accès existe, vérifiez que le chemin d'accès est correct
et réessayez.
```

```text
php artisan test tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php
```

Exit code: `1`

```text
php : Le terme «php» n'est pas reconnu comme nom d'applet de commande, fonction, fichier de script ou programme
exécutable. Vérifiez l'orthographe du nom, ou si un chemin d'accès existe, vérifiez que le chemin d'accès est correct
et réessayez.
```

### GREEN command attempt (after implementation)

```text
php artisan migrate:fresh
php artisan test tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php
```

Exit code: `1` for each command. Both produced the same `php ... n'est pas reconnu ... CommandNotFoundException` error before Laravel started.

## Static Verification and Self-Review

Commands run:

```text
git diff --check
git diff --cached --check
```

Result: both produced no output and exit code `0`.

The implementation was also manually checked line-by-line against `task-2-brief.md` after creation. Confirmed:

- Projects use the requested user cascade, field sizes/defaults, timestamp-with-time-zone fields, unique key, and three lookup indexes.
- Tasks use `restrictOnDelete()` for projects, `nullOnDelete()` for parent tasks, `softDeletesTz()`, date-only `due_on`, and all requested indexes.
- Labels/pivot/activity migrations use the requested association-only cascades, history-preserving activity FKs, nullable `jsonb` payloads, and indexes.
- All seven enums have the required namespaces, string backing, case ordering, and persisted values.

## Files Changed

- `database/migrations/2026_08_21_000000_create_user_preferences_table.php`
- `database/migrations/2026_08_27_000001_create_projects_table.php`
- `database/migrations/2026_08_27_000002_create_tasks_table.php`
- `database/migrations/2026_08_27_000003_create_labels_table.php`
- `database/migrations/2026_08_27_000004_create_task_label_table.php`
- `database/migrations/2026_08_27_000005_create_task_activities_table.php`
- `app/Domain/Projects/Enums/ProjectStatus.php`
- `app/Domain/Tasks/Enums/TaskStatus.php`
- `app/Domain/Tasks/Enums/TaskPriority.php`
- `app/Domain/Activity/Enums/TaskActivityType.php`
- `app/Domain/Identity/Enums/ThemePreference.php`
- `app/Domain/Identity/Enums/DensityPreference.php`
- `app/Domain/Identity/Enums/WeekStartDay.php`

## Concerns

- Required migration and focused schema/status tests did not run because `php` is not recognized on PATH. Install or expose PHP, then run the two mandated commands above.
- Ownership tests remain intentionally pending until later models and factories exist, as required by the task brief.
