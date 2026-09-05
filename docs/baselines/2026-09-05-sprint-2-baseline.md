# PlanOps Sprint 2 baseline — 2026-09-05

**Purpose:** record the DYX-000 starting point before collaboration migrations or authorization changes.

**Status:** Captured; repeatability variance resolved. The release gate remains red on pre-existing failures.

**Authority:** [Sprint 2 specification](../PlanOps_Sprint_2.md)

## Environment

| Tool | Version / result |
| --- | --- |
| PHP | 8.3.33 (`C:\Users\aliha\.config\herd\bin\php83\php.exe`) |
| Composer | 2.10.2 |
| Node.js | v24.12.0 |
| npm | 11.6.2 |
| Framework | Laravel 13, read from repository contract |
| Database target | PostgreSQL; current test configuration is also exercised through the repository test setup |

## Command results

### PHP test suite

Command:

```text
php artisan test --no-ansi --compact
```

Latest failure-inventory run:

- Exit code: `2`.
- Tests: `64 failed, 1 skipped, 186 passed`.
- Assertions: `1,028`.
- Duration: approximately `20s`.
- Non-blocking PHP warnings reported unused non-compound `use` statements in `TaskActivityRecorderTest.php:12`, `TaskKeyTest.php:8`, `TaskActivityImmutabilityTest.php:8`, `ProjectConsoleTest.php:7-9`, and `TaskMetadataTest.php:23`.

Reproducibility observation: two fresh full-suite runs after fixture hardening both reported `64 failed, 1 skipped, 186 passed` with `1,028` assertions and exit code `2`. The earlier one-test variance was caused by random task numbers in the My Work `task_key` sort fixture; implicit timestamps and due dates also made the sort matrix under-specified. Those fixtures now provide explicit ordering inputs. The full-suite release gate remains red because the remaining failures are pre-existing contract and bootstrap failures.

All 64 failures are classified as `fix-before-P0` with owner `PlanOps engineering`. No failure is quarantined yet.

## Failure inventory from the latest run

The counts below are grouped by test file. The detailed test output remains the primary diagnostic record; this table keeps every observed failing location visible while work proceeds.

| Test file | Failures | Initial classification / evidence |
| --- | ---: | --- |
| `Tests\Feature\Activity\GlobalActivityFeedTest` | 1 | Feature copy contract: expected `Status changed`, rendered text uses a different capitalization. |
| `Tests\Feature\Database\SeedReproducibilityTest` | 1 | Seed snapshot is not stable between repeated seed runs. |
| `Tests\Feature\Export\ExportTest` | 1 | Export content/scope assertion does not contain expected `OWN`. |
| `Tests\Feature\MyWork\MyWorkFiltersTest` | 1 | `this_week` result differs from expected task set. |
| `Tests\Feature\MyWork\MyWorkSortingTest` | 0 | Sort fixture stabilized with explicit task numbers and timestamps; all safe-sort cases pass. |
| `Tests\Feature\Projects\ProjectBoardTest` | 1 | Board rendering/metadata assertion mismatch. |
| `Tests\Feature\Projects\ProjectConsoleTest` | 5 | Project console feature-contract assertions; inspect individual cases before changing behavior. |
| `Tests\Feature\Projects\ProjectManagementTest` | 1 | Project management feature-contract assertion. |
| `Tests\Feature\Projects\ProjectOverviewTest` | 1 | Project overview feature-contract assertion. |
| `Tests\Feature\Search\SearchQueryTest` | 4 | Search query/result contract assertions. |
| `Tests\Feature\Settings\TimezoneBoundaryTest` | 1 | Timezone boundary assertion. |
| `Tests\Feature\Tasks\ProjectTaskListTest` | 2 | Undefined `$project` in `resources\views\components\filters\project-task-filters.blade.php` and redirect query-order mismatch; sort matrix now passes with explicit fixture inputs. |
| `Tests\Unit\Domain\Activity\TaskActivityRecorderTest` | 4 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Analytics\AnalyticsMetricTest` | 2 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Dashboard\DashboardMetricTest` | 2 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Identity\UserPeriodResolverTest` | 5 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Labels\LabelNormalizationTest` | 2 | Facade bootstrap failure plus tab/whitespace normalization mismatch at `tests\Unit\Domain\Labels\LabelNormalizationTest.php:19`. |
| `Tests\Unit\Domain\Projects\ProjectKeyTest` | 14 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Projects\ProjectProgressTest` | 3 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Tasks\OverdueTaskTest` | 5 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Tasks\ProjectBoardQueryTest` | 2 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Tasks\ReorderTasksTest` | 3 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |
| `Tests\Unit\Domain\Tasks\TaskKeyTest` | 4 | `A facade root has not been set.` through `database\factories\UserFactory.php:31`. |

## Frontend build

Command:

```text
npm.cmd run build
```

Result:

- Exit code: `0`.
- Vite: `7.3.6`.
- Modules transformed: `4`.
- Duration: `2.78s`.

## Route inventory

Command:

```text
php artisan route:list --except-vendor
```

Result:

- Exit code: `0`.
- Routes shown: `52`.
- Existing routes cover the single-user project/task, activity, analytics, search, export, dashboard, My Work, and settings surfaces.
- No collaboration-specific Team, invitation, membership, ownership-transfer, assignment, or notification routes exist yet.

## Contract reconciliation decisions

The following documents remain useful historical references for the pre-collaboration product. Their conflicting ownership and feature-boundary claims are explicitly superseded by the Sprint 2 authority document:

- `planops-complete-spec.md`
- `docs/architecture/domain-contracts.md`
- `docs/architecture/stack.md`
- `docs/ui/screen-spec.md`
- `docs/superpowers/plans/2026-08-20-planops-implementation.md`

Non-conflicting v1 contracts remain valid. The Sprint 2 authority controls active membership, role values, invitation lifecycle, assignment, actor history, project-scoped labels, notification timing, and collaboration-aware access.

## DYX-000 decision

DYX-000 is accepted as a repeatable baseline gate. The authority-document banners are synchronized, the build and route inventories pass, and two fresh full-suite runs produce the same result. The PHP release gate remains red until the 64 named pre-existing failures are resolved or explicitly quarantined; this does not block starting schema work under DYX-001.
