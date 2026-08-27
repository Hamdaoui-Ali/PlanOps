# DYX-004 Activity Recorder Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the append-only PlanOps activity recorder, normalized event payloads, immutable activity models, and user-scoped chronological feed query.

**Architecture:** Future domain Actions write activity only through `TaskActivityRecorder`, which copies ownership context from a persisted `Task` and normalizes enums, dates, metadata, and sensitive text. `TaskActivity` remains readable but rejects model updates and deletes. `TaskActivityFeedQuery` owns user-scoped, deterministic, paginated reads and accepts UTC bounds resolved by a later period service.

**Tech Stack:** Laravel 13; PHP 8.3; Eloquent; PostgreSQL `jsonb` in production; SQLite in-memory tests; Pest/PHPUnit; Laravel factories; immutable Carbon date-time values.

**Spec:** [`docs/superpowers/specs/2026-08-27-dyx-004-activity-recorder-design.md`](../specs/2026-08-27-dyx-004-activity-recorder-design.md)

## Global Constraints

- Use the primary baseline exactly: **Laravel 13 / PHP 8.3**.
- Scope every user-owned query, relationship, action, policy, and search by `user_id`; cross-user resources may return `404`.
- Keep the persistent core to `users`, `user_preferences`, `projects`, `tasks`, `labels`, `task_label`, and `task_activities`; DYX-004 does not add a table or migration.
- Record meaningful mutations through append-only `TaskActivity`; do not record page views, filters, cache refreshes, autosave keystrokes, or inferred computer activity.
- Store backed enum values as stable strings and date-only values as `YYYY-MM-DD`; convert `DateTimeInterface` payloads to UTC ISO-8601 strings.
- Do not copy full task `title` or `description` text into generic `TASK_UPDATED` old/new values or activity metadata.
- Preserve `TaskActivityType::TASK_MOVED_PROJECT` in the contract but keep it unreachable from the v1 UI and routes.
- Keep task/activity context valid for soft-deleted tasks; soft deletion must not remove normal activity history.
- Accept `from` as an inclusive UTC bound and `until` as an exclusive UTC bound; timezone resolution belongs to DYX-015.
- Default global activity pagination to 50 rows and use deterministic ordering with an id tie-breaker.
- Do not add activity pages, dashboard queries, analytics, task Actions, project Actions, queues, schedulers, WebSockets, external search, or visual design work in this slice.

## Repository and File Map

| File | Responsibility in this plan |
| --- | --- |
| `app/Domain/Activity/Services/TaskActivityRecorder.php` | Explicit activity write boundary and recursive payload normalization. |
| `app/Domain/Activity/Models/TaskActivity.php` | Existing relationships/casts plus model-level append-only guards and `created_at` casting. |
| `app/Domain/Activity/Queries/TaskActivityFeedQuery.php` | User-scoped global feed pagination and oldest-first task timeline reads. |
| `tests/Unit/Domain/Activity/TaskActivityRecorderTest.php` | Recorder context, normalization, redaction, and unsaved-task contract. |
| `tests/Feature/Domain/Activity/TaskActivityImmutabilityTest.php` | Update/delete protection for persisted activity rows. |
| `tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php` | Feed ownership, filters, pagination, soft-deleted task context, and timeline ordering. |
| `docs/superpowers/plans/2026-08-20-planops-implementation.md` | Mark the completed DYX-004 checklist items after the implementation gate passes. |

## Dependency Order

```text
Task 1: failing recorder contract tests
    ↓
Task 2: normalized recorder implementation
    ↓
Task 3: append-only model guards
    ↓
Task 4: feed query and ownership tests
    ↓
Task 5: full verification and master-plan synchronization
```

No task introduces a UI dependency. DYX-005 and DYX-006 will consume the
recorder inside their own transaction boundaries after this plan is complete.

---

### Task 1: Define the failing recorder contract

**Files:**

- Create: `tests/Unit/Domain/Activity/TaskActivityRecorderTest.php`
- Read: `app/Domain/Activity/Enums/TaskActivityType.php`
- Read: `app/Domain/Tasks/Enums/TaskPriority.php`
- Read: `app/Domain/Tasks/Enums/TaskStatus.php`
- Read: `app/Domain/Tasks/Models/Task.php`

**Interfaces:**

- Consumes: DYX-003 `User`, `Project`, `Task`, `TaskActivityType`, `TaskPriority`, and `TaskStatus` factories/models.
- Produces: executable examples for `TaskActivityRecorder::record()` that Task 2 must satisfy exactly.

- [ ] **Step 1: Write the failing test file** — create the refresh-database unit tests with this contract:

```php
<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Services\TaskActivityRecorder;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Enums\TaskPriority;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use LogicException;

uses(RefreshDatabase::class);

test('records task ownership context and stable enum payload values', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::STATUS_CHANGED,
        'status',
        TaskStatus::IN_PROGRESS,
        TaskStatus::DONE,
    );

    expect($activity->user_id)->toBe($owner->id);
    expect($activity->project_id)->toBe($project->id);
    expect($activity->task_id)->toBe($task->id);
    expect($activity->getRawOriginal('event_type'))->toBe('STATUS_CHANGED');
    expect($activity->old_value)->toBe(['status' => 'IN_PROGRESS']);
    expect($activity->new_value)->toBe(['status' => 'DONE']);
});

test('redacts title and description values from generic updates', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::TASK_UPDATED,
        'description',
        'private old description',
        'private new description',
        ['description' => 'private metadata', 'is_reopen' => true],
    );

    expect($activity->old_value)->toBeNull();
    expect($activity->new_value)->toBeNull();
    expect($activity->metadata)->toBe(['is_reopen' => true]);
});

test('normalizes nested enums and date-times while preserving metadata booleans', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    $activity = (new TaskActivityRecorder)->record(
        $task,
        TaskActivityType::TASK_UPDATED,
        'priority',
        ['priority' => TaskPriority::MEDIUM],
        ['priority' => TaskPriority::HIGH],
        [
            'is_reopen' => false,
            'changed_at' => Carbon::parse('2026-08-27 14:00:00', 'Africa/Casablanca'),
        ],
    );

    expect($activity->old_value)->toBe(['priority' => 'MEDIUM']);
    expect($activity->new_value)->toBe(['priority' => 'HIGH']);
    expect($activity->metadata['is_reopen'])->toBeFalse();
    expect($activity->metadata['changed_at'])->toBe('2026-08-27T13:00:00+00:00');
});

test('rejects an unsaved task because it has no historical context', function (): void {
    expect(fn (): mixed => (new TaskActivityRecorder)->record(
        new Task,
        TaskActivityType::TASK_CREATED,
        null,
        null,
        ['status' => TaskStatus::NOT_STARTED],
    ))->toThrow(LogicException::class);
});
```

- [ ] **Step 2: Run the focused tests to prove the contract is red**

Run:

```powershell
php artisan test tests/Unit/Domain/Activity/TaskActivityRecorderTest.php
```

Expected result: FAIL because `App\Domain\Activity\Services\TaskActivityRecorder` does not exist. If the test errors for a typo or invalid assertion instead, correct the test and rerun until the failure is caused by the missing production service.

- [ ] **Step 3: Commit the failing contract tests**

```powershell
git add -- tests/Unit/Domain/Activity/TaskActivityRecorderTest.php
git commit -m "test: define DYX-004 activity recorder contract"
```

The commit is intentionally test-only and becomes the red baseline for Task 2.

---

### Task 2: Implement normalized activity recording

**Files:**

- Create: `app/Domain/Activity/Services/TaskActivityRecorder.php`
- Test: `tests/Unit/Domain/Activity/TaskActivityRecorderTest.php`

**Interfaces:**

- Consumes: persisted `Task` instances and the complete `TaskActivityType` enum.
- Produces: `TaskActivityRecorder::record(Task $task, TaskActivityType $type, ?string $field, mixed $oldValue, mixed $newValue, array $metadata = []): TaskActivity`.

- [ ] **Step 1: Add the minimal recorder class** — implement this public boundary and private helpers:

```php
<?php

namespace App\Domain\Activity\Services;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Tasks\Models\Task;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use LogicException;

final class TaskActivityRecorder
{
    private const REDACTED_FIELDS = ['title', 'description'];

    public function record(
        Task $task,
        TaskActivityType $type,
        ?string $field,
        mixed $oldValue,
        mixed $newValue,
        array $metadata = [],
    ): TaskActivity {
        if (! $task->exists || ! $task->getKey()) {
            throw new LogicException('Task activity requires a persisted task.');
        }

        $redactValues = $type === TaskActivityType::TASK_UPDATED
            && in_array(strtolower((string) $field), self::REDACTED_FIELDS, true);

        return TaskActivity::query()->create([
            'user_id' => $task->user_id,
            'project_id' => $task->project_id,
            'task_id' => $task->getKey(),
            'event_type' => $type,
            'field' => $field,
            'old_value' => $redactValues ? null : $this->normalizePayload($oldValue, $field),
            'new_value' => $redactValues ? null : $this->normalizePayload($newValue, $field),
            'metadata' => $this->normalizeMetadata($metadata),
        ])->refresh();
    }

    private function normalizePayload(mixed $value, ?string $field): mixed
    {
        $normalized = $this->normalizeValue($value);

        return $field !== null && ! is_array($normalized)
            ? [$field => $normalized]
            : $normalized;
    }

    private function normalizeMetadata(array $metadata): array
    {
        return $this->normalizeMetadataValue($metadata);
    }

    private function normalizeMetadataValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $this->normalizeValue($value);
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), self::REDACTED_FIELDS, true)) {
                continue;
            }

            $normalized[$key] = $this->normalizeMetadataValue($item);
        }

        return $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc()->toIso8601String();
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        return $normalized;
    }
}
```

The implementation must use the task as the only source of ownership ids, keep `TASK_MOVED_PROJECT` accepted by the enum-backed argument without adding a route, and allow a cleared date to persist as `['due_on' => null]` because `null` is wrapped when a field is supplied.

- [ ] **Step 2: Run the recorder tests to verify the green transition**

Run:

```powershell
php artisan test tests/Unit/Domain/Activity/TaskActivityRecorderTest.php
```

Expected result: PASS with 5 tests and no warnings. If a failure occurs, change the recorder rather than weakening the test contract.

- [ ] **Step 3: Run the existing activity ownership regression tests**

Run:

```powershell
php artisan test tests/Feature/Authorization/OwnershipScopeTest.php tests/Feature/Database/SchemaInvariantTest.php
```

Expected result: PASS. The new service must not change the seven-table schema, existing ownership scopes, or soft-deleted task relationship.

- [ ] **Step 4: Commit the recorder implementation**

```powershell
git add -- app/Domain/Activity/Services/TaskActivityRecorder.php
git commit -m "feat: add normalized task activity recorder"
```

---

### Task 3: Enforce append-only activity models

**Files:**

- Modify: `app/Domain/Activity/Models/TaskActivity.php`
- Create: `tests/Feature/Domain/Activity/TaskActivityImmutabilityTest.php`

**Interfaces:**

- Consumes: the existing Eloquent `TaskActivity` model and `TaskActivityFactory`.
- Produces: an activity model whose normal `update()` and `delete()` operations throw `LogicException`, while new creation through the recorder/factory remains valid.

- [ ] **Step 1: Write the failing immutability tests** — add this test file before changing the model:

```php
<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;

uses(RefreshDatabase::class);

function immutableActivityFixture(): TaskActivity
{
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);

    return TaskActivity::factory()->forTask($task)->create();
}

test('persisted activity rows reject model updates', function (): void {
    $activity = immutableActivityFixture();

    expect(fn (): mixed => $activity->update(['field' => 'status']))
        ->toThrow(LogicException::class, 'append-only');
    expect($activity->fresh()->field)->toBeNull();
});

test('persisted activity rows reject model deletes', function (): void {
    $activity = immutableActivityFixture();

    expect(fn (): mixed => $activity->delete())
        ->toThrow(LogicException::class, 'append-only');
    expect(TaskActivity::query()->whereKey($activity->getKey())->exists())->toBeTrue();
});

test('activity created_at is exposed as an immutable date-time', function (): void {
    $activity = immutableActivityFixture();

    expect($activity->created_at)->toBeInstanceOf(DateTimeImmutable::class);
});
```

- [ ] **Step 2: Run the immutability tests to prove they are red**

Run:

```powershell
php artisan test tests/Feature/Domain/Activity/TaskActivityImmutabilityTest.php
```

Expected result: FAIL because the model currently permits updates/deletes and does not cast `created_at`.

- [ ] **Step 3: Add model guards and the timestamp cast** — update `TaskActivity` without removing its existing relationships, fillable creation attributes, or enum/JSON casts:

```php
use LogicException;

protected static function booted(): void
{
    static::updating(static function (): never {
        throw new LogicException('Task activity records are append-only.');
    });

    static::deleting(static function (): never {
        throw new LogicException('Task activity records are append-only.');
    });
}

protected function casts(): array
{
    return [
        'event_type' => TaskActivityType::class,
        'old_value' => 'array',
        'new_value' => 'array',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
```

Keep the existing `$timestamps = false` setting because the table has no `updated_at` column. The guards cover normal Eloquent model operations; no application code may use a query-builder bulk update/delete against `task_activities`.

- [ ] **Step 4: Run the immutability and recorder tests**

Run:

```powershell
php artisan test tests/Feature/Domain/Activity/TaskActivityImmutabilityTest.php tests/Unit/Domain/Activity/TaskActivityRecorderTest.php
```

Expected result: PASS with 8 tests and no warnings.

- [ ] **Step 5: Commit the append-only boundary**

```powershell
git add -- app/Domain/Activity/Models/TaskActivity.php tests/Feature/Domain/Activity/TaskActivityImmutabilityTest.php
git commit -m "feat: enforce append-only task activity models"
```

---

### Task 4: Add the user-scoped feed and task timeline query

**Files:**

- Create: `app/Domain/Activity/Queries/TaskActivityFeedQuery.php`
- Create: `tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php`

**Interfaces:**

- Consumes: `TaskActivity::ownedBy()`, the `project` and soft-deleted `task` relations, `TaskActivityType`, `User`, and `Task`.
- Produces:

```php
paginate(User|int $owner, array $filters = [], int $perPage = 50): LengthAwarePaginator
forTask(User|int $owner, Task|int $task): Collection
```

- [ ] **Step 1: Write failing feed/query tests** — create tests for user scope, event/project/task/UTC-bound filters, default page size, soft-deleted task context, newest-first feed ordering, and oldest-first task timeline ordering:

```php
<?php

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Activity\Queries\TaskActivityFeedQuery;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('global activity feed scopes rows before applying filters', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ownerProject = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($other)->create(['key' => 'OPS']);
    $ownerTask = Task::factory()->forProject($ownerProject)->create(['number' => 1]);
    $otherTask = Task::factory()->forProject($otherProject)->create(['number' => 1]);
    $ownerActivity = TaskActivity::factory()->forTask($ownerTask)->statusChanged()->create([
        'created_at' => Carbon::parse('2026-08-27 12:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($otherTask)->statusChanged()->create([
        'created_at' => Carbon::parse('2026-08-27 13:00:00 UTC'),
    ]);

    $feed = (new TaskActivityFeedQuery)->paginate($owner, [
        'project_id' => $otherProject->id,
        'task_id' => $otherTask->id,
        'event_type' => TaskActivityType::STATUS_CHANGED,
    ]);

    expect($feed->total())->toBe(0);
    expect((new TaskActivityFeedQuery)->paginate($owner)->getCollection()->pluck('id')->all())
        ->toBe([$ownerActivity->id]);
});

test('global activity feed filters UTC bounds and orders newest first', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $older = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 09:00:00 UTC'),
    ]);
    $newer = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 11:00:00 UTC'),
    ]);

    $feed = (new TaskActivityFeedQuery)->paginate($owner, [
        'from' => Carbon::parse('2026-08-27 09:00:00 UTC'),
        'until' => Carbon::parse('2026-08-27 12:00:00 UTC'),
    ]);

    expect($feed->getCollection()->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

test('global activity pagination defaults to fifty rows and keeps deleted task context', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    TaskActivity::factory()->count(51)->forTask($task)->create();
    $task->delete();

    $feed = (new TaskActivityFeedQuery)->paginate($owner);

    expect($feed->perPage())->toBe(50);
    expect($feed->first()->task)->not->toBeNull();
    expect($feed->first()->task->trashed())->toBeTrue();
});

test('task activity timeline is owner-scoped and oldest first', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($other)->create(['key' => 'OPS']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $otherTask = Task::factory()->forProject($otherProject)->create(['number' => 1]);
    $older = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 09:00:00 UTC'),
    ]);
    $newer = TaskActivity::factory()->forTask($task)->create([
        'created_at' => Carbon::parse('2026-08-27 10:00:00 UTC'),
    ]);
    TaskActivity::factory()->forTask($otherTask)->create();

    $timeline = (new TaskActivityFeedQuery)->forTask($owner, $task);

    expect($timeline->pluck('id')->all())->toBe([$older->id, $newer->id]);
    expect((new TaskActivityFeedQuery)->forTask($owner, $otherTask))->toHaveCount(0);
});
```

- [ ] **Step 2: Run the feed tests to prove they are red**

Run:

```powershell
php artisan test tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php
```

Expected result: FAIL because `TaskActivityFeedQuery` does not exist. Correct test setup errors before proceeding.

- [ ] **Step 3: Implement the query service** — create a final query class with these concrete rules:

```php
<?php

namespace App\Domain\Activity\Queries;

use App\Domain\Activity\Enums\TaskActivityType;
use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class TaskActivityFeedQuery
{
    public function paginate(
        User|int $owner,
        array $filters = [],
        int $perPage = 50,
    ): LengthAwarePaginator {
        $perPage = min(50, max(1, $perPage));
        $eventType = $filters['event_type'] ?? null;
        $eventType = $eventType instanceof TaskActivityType ? $eventType->value : $eventType;

        return TaskActivity::query()
            ->ownedBy($owner)
            ->with([
                'project:id,user_id,name,key',
                'task:id,user_id,project_id,number,title,deleted_at',
            ])
            ->when(array_key_exists('project_id', $filters), fn ($query) => $query->where('project_id', $filters['project_id']))
            ->when(array_key_exists('task_id', $filters), fn ($query) => $query->where('task_id', $filters['task_id']))
            ->when($eventType !== null, fn ($query) => $query->where('event_type', $eventType))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['until'] ?? null, fn ($query, $until) => $query->where('created_at', '<', $until))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function forTask(User|int $owner, Task|int $task): Collection
    {
        $taskId = $task instanceof Task ? $task->getKey() : $task;

        return TaskActivity::query()
            ->ownedBy($owner)
            ->where('task_id', $taskId)
            ->with([
                'project:id,user_id,name,key',
                'task:id,user_id,project_id,number,title,deleted_at',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
```

The owner scope must remain before the optional predicates in the query chain, and filters must be bound values rather than interpolated SQL. The task relation already uses `withTrashed()`; the selected columns must include the foreign keys and `deleted_at` required to hydrate that relation.

- [ ] **Step 4: Run the feed tests to verify the green transition**

Run:

```powershell
php artisan test tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php
```

Expected result: PASS with 4 tests and no warnings.

- [ ] **Step 5: Run the complete activity slice**

Run:

```powershell
php artisan test tests/Unit/Domain/Activity tests/Feature/Domain/Activity tests/Feature/Authorization/OwnershipScopeTest.php
```

Expected result: PASS with all activity and existing ownership tests green.

- [ ] **Step 6: Commit the feed query**

```powershell
git add -- app/Domain/Activity/Queries/TaskActivityFeedQuery.php tests/Feature/Domain/Activity/TaskActivityOwnershipTest.php
git commit -m "feat: add user-scoped activity feed query"
```

---

### Task 5: Verify the slice and synchronize the master implementation plan

**Files:**

- Modify: `docs/superpowers/plans/2026-08-20-planops-implementation.md:168-173`

**Interfaces:**

- Consumes: all DYX-004 application code and tests from Tasks 1–4.
- Produces: a verified DYX-004 slice and a master plan whose DYX-004 checklist reflects the committed implementation.

- [ ] **Step 1: Run the complete application test suite**

Run:

```powershell
php artisan test --parallel
```

Expected result: PASS with zero failures and zero errors.

- [ ] **Step 2: Run static formatting and diff checks**

Run:

```powershell
vendor/bin/pint --test
git diff --check
```

Expected result: both commands exit with code 0 and report no formatting or whitespace violations.

- [ ] **Step 3: Mark DYX-004 implementation steps complete** — change only the six DYX-004 checklist markers in `docs/superpowers/plans/2026-08-20-planops-implementation.md` from `[ ]` to `[x]`:

```markdown
- [x] **Step 1: Write failing recorder tests**
- [x] **Step 2: Run ...**
- [x] **Step 3: Implement normalized recording**
- [x] **Step 4: Enforce append-only application behavior**
- [x] **Step 5: Run the activity tests**
- [x] **Step 6: Commit**
```

Keep the task text and all DYX-005–DYX-021 checkboxes unchanged. The master plan remains the dependency roadmap; this synchronization does not claim the full product is complete.

- [ ] **Step 4: Re-run the targeted activity suite after the documentation change**

Run:

```powershell
php artisan test tests/Unit/Domain/Activity tests/Feature/Domain/Activity
```

Expected result: PASS with no application regressions.

- [ ] **Step 5: Commit the verification record**

```powershell
git add -- docs/superpowers/plans/2026-08-20-planops-implementation.md
git commit -m "docs: mark DYX-004 activity foundation complete"
```

## Final Verification Checklist

- [ ] `TaskActivityRecorder` copies context only from the persisted task.
- [ ] `TaskActivityType` and nested backed enums persist as stable strings.
- [ ] UTC date-time payloads and date-only strings retain the documented formats.
- [ ] Generic title/description updates do not persist full text in old/new values or metadata.
- [ ] `metadata.is_reopen` remains an explicit boolean and is never inferred.
- [ ] All eleven activity event types remain accepted by the recorder boundary, while `TASK_MOVED_PROJECT` is not wired to a v1 UI.
- [ ] Existing activity rows reject normal model update/delete operations.
- [ ] Soft-deleted task context remains readable through activity relations.
- [ ] Global feed and task timeline queries scope by owner before filters.
- [ ] Global feed defaults to 50 rows and is newest-first; task timelines are oldest-first.
- [ ] `from`/`until` are UTC, inclusive/exclusive bounds and do not perform timezone resolution.
- [ ] No activity routes, pages, analytics, task Actions, project Actions, or new tables were introduced.
- [ ] `php artisan test --parallel`, `vendor/bin/pint --test`, and `git diff --check` pass after the final commit.

## Planned Commit History

1. `test: define DYX-004 activity recorder contract`
2. `feat: add normalized task activity recorder`
3. `feat: enforce append-only task activity models`
4. `feat: add user-scoped activity feed query`
5. `docs: mark DYX-004 activity foundation complete`
