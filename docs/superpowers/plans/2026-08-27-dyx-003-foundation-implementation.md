# DYX-003 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the typed, relational, user-owned PlanOps foundation required by project, task, activity, label, dashboard, and analytics features.

**Architecture:** Extend the existing Laravel 13 modular monolith with five domain migrations, seven backed enums, focused Eloquent models, explicit ownership scopes, and deterministic factories. Keep cross-record rules in the application layer, use PostgreSQL-first persistence types, and keep the existing SQLite test configuration compatible.

**Tech Stack:** PHP 8.3, Laravel 13, Eloquent ORM, PostgreSQL production schema, SQLite in-memory tests, Pest/PHPUnit, Faker factories, Blade/Tailwind/Vite repository conventions.

**Spec:** `docs/superpowers/specs/2026-08-27-dyx-003-foundation-design.md`

## Global Constraints

- Production baseline is Laravel 13 with PHP 8.3 and PostgreSQL.
- Production timestamps use UTC-capable `TIMESTAMPTZ`; date-only fields use `DATE`.
- Activity payloads use nullable PostgreSQL `JSONB` columns named `old_value`, `new_value`, and `metadata`.
- The seven core tables are `users`, `user_preferences`, `projects`, `tasks`, `labels`, `task_label`, and `task_activities`.
- Persisted state uses the exact backed enum values in the design spec; `OVERDUE` is computed and is not a status.
- Every user-owned query must have an explicit ownership path or an `ownedBy()` scope.
- Task hierarchy is structurally one-level and same-project; mutation enforcement belongs to later Actions and policies.
- Task numbering is project-local, never reused, and allocated transactionally by the later task-creation Action.
- Task history remains available after normal project archive and task soft deletion.
- This slice adds no HTTP routes, mutation Actions, policies, dashboards, analytics queries, queues, schedulers, WebSockets, teams, assignees, timers, comments, or integrations.
- Tests use the repository's SQLite in-memory configuration; do not replace it with a second database setup.
- The current shell has Node 22 and npm, but `php` and Composer are not on `PATH`; verify the toolchain before running PHP tests and report that environment blocker without changing repository architecture to work around it.
- Save the work in several logical commits: tests, persistence/enums, models/scopes, and factories/seeding/final green verification.

---

## File map

**Tests first:**

- Create `tests/Feature/Database/SchemaInvariantTest.php` for table, column, constraint, and index invariants.
- Create `tests/Unit/Domain/Tasks/TaskStatusTest.php` for the complete status set and category mapping.
- Create `tests/Feature/Authorization/OwnershipScopeTest.php` for user-owned query boundaries and valid relationship context.

**Persistence and enums:**

- Modify `database/migrations/2026_08_21_000000_create_user_preferences_table.php` to use timezone-aware timestamps without changing its migration identity.
- Create `database/migrations/2026_08_27_000001_create_projects_table.php`.
- Create `database/migrations/2026_08_27_000002_create_tasks_table.php`.
- Create `database/migrations/2026_08_27_000003_create_labels_table.php`.
- Create `database/migrations/2026_08_27_000004_create_task_label_table.php`.
- Create `database/migrations/2026_08_27_000005_create_task_activities_table.php`.
- Create `app/Domain/Projects/Enums/ProjectStatus.php`.
- Create `app/Domain/Tasks/Enums/TaskStatus.php`.
- Create `app/Domain/Tasks/Enums/TaskPriority.php`.
- Create `app/Domain/Activity/Enums/TaskActivityType.php`.
- Create `app/Domain/Identity/Enums/ThemePreference.php`.
- Create `app/Domain/Identity/Enums/DensityPreference.php`.
- Create `app/Domain/Identity/Enums/WeekStartDay.php`.

**Models and ownership primitives:**

- Modify `app/Models/User.php` with ownership relations.
- Modify `app/Domain/Identity/Models/UserPreference.php` with enum casts.
- Create `app/Domain/Projects/Models/Project.php`.
- Create `app/Domain/Tasks/Models/Task.php`.
- Create `app/Domain/Labels/Models/Label.php`.
- Create `app/Domain/Activity/Models/TaskActivity.php`.

**Factories and deterministic seed:**

- Create `database/factories/UserPreferenceFactory.php`.
- Create `database/factories/ProjectFactory.php`.
- Create `database/factories/TaskFactory.php`.
- Create `database/factories/LabelFactory.php`.
- Create `database/factories/TaskActivityFactory.php`.
- Modify `database/seeders/DatabaseSeeder.php`.

## Task 1: Lock the failing DYX-003 invariants

**Files:**

- Create: `tests/Feature/Database/SchemaInvariantTest.php`
- Create: `tests/Unit/Domain/Tasks/TaskStatusTest.php`
- Create: `tests/Feature/Authorization/OwnershipScopeTest.php`

**Interfaces:**

- Consumes: existing `User` factory and `RefreshDatabase` test setup.
- Produces: the exact table/index names, `TaskStatus::category()` contract, and `ownedBy(User|int)` scope contract that implementation must satisfy.

- [ ] **Step 1: Add the schema test helpers and table assertions.**

Use `Schema::hasTable`, `Schema::hasColumn`, and `Schema::getIndexes` so the test checks database structure rather than model metadata. Keep the index assertion reusable:

```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function indexDefinition(string $table, string $name): array
{
    return collect(Schema::getIndexes($table))
        ->firstWhere('name', $name) ?? [];
}

test('the PlanOps foundation contains the seven core tables', function () {
    foreach ([
        'users',
        'user_preferences',
        'projects',
        'tasks',
        'labels',
        'task_label',
        'task_activities',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

test('the foundation contains required columns and lifecycle fields', function () {
    foreach ([
        'projects' => [
            'user_id', 'name', 'key', 'description', 'status', 'color', 'icon',
            'start_on', 'target_on', 'next_task_number', 'archived_at',
        ],
        'tasks' => [
            'user_id', 'project_id', 'parent_task_id', 'number', 'title',
            'description', 'status', 'priority', 'due_on', 'position',
            'first_started_at', 'completed_at', 'cancelled_at',
            'status_changed_at', 'deleted_at',
        ],
        'labels' => ['user_id', 'name', 'normalized_name', 'color'],
        'task_label' => ['task_id', 'label_id', 'created_at'],
        'task_activities' => [
            'user_id', 'project_id', 'task_id', 'event_type', 'field',
            'old_value', 'new_value', 'metadata', 'created_at',
        ],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))
                ->toBeTrue("{$table}.{$column} is required");
        }
    }
});

test('the foundation contains the documented unique keys', function () {
    expect(indexDefinition('projects', 'projects_user_id_key_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('tasks', 'tasks_project_id_number_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('labels', 'labels_user_id_normalized_name_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('task_label', 'task_label_task_id_label_id_unique')['unique'] ?? false)->toBeTrue();
    expect(indexDefinition('user_preferences', 'user_preferences_user_id_unique')['unique'] ?? false)->toBeTrue();
});

test('the foundation contains the documented lookup indexes', function () {
    expect(indexDefinition('projects', 'projects_user_id_status_index'))->not->toBeEmpty();
    expect(indexDefinition('projects', 'projects_user_id_archived_at_index'))->not->toBeEmpty();
    expect(indexDefinition('projects', 'projects_user_id_updated_at_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_status_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_project_id_status_position_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_project_id_parent_task_id_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_priority_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_due_on_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_user_id_updated_at_index'))->not->toBeEmpty();
    expect(indexDefinition('tasks', 'tasks_parent_task_id_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_user_id_created_at_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_project_id_created_at_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_task_id_created_at_index'))->not->toBeEmpty();
    expect(indexDefinition('task_activities', 'task_activities_event_type_created_at_index'))->not->toBeEmpty();
});

test('activity payload columns are nullable JSON-capable columns', function () {
    foreach (['old_value', 'new_value', 'metadata'] as $column) {
        expect(Schema::hasColumn('task_activities', $column))->toBeTrue();
    }
});

test('tasks expose soft deletion support', function () {
    expect(Schema::hasColumn('tasks', 'deleted_at'))->toBeTrue();
});
```

- [ ] **Step 2: Add the exact status and category test.**

Create `tests/Unit/Domain/Tasks/TaskStatusTest.php` with this contract:

```php
<?php

use App\Domain\Tasks\Enums\TaskStatus;

test('TaskStatus contains the complete workflow', function () {
    expect(array_column(TaskStatus::cases(), 'value'))->toBe([
        'BACKLOG',
        'NOT_STARTED',
        'IN_PROGRESS',
        'IN_REVIEW',
        'BLOCKED',
        'DONE',
        'CANCELLED',
    ]);
});

test('TaskStatus maps to stable analytics categories', function () {
    expect(TaskStatus::BACKLOG->category())->toBe('PLANNED');
    expect(TaskStatus::NOT_STARTED->category())->toBe('PLANNED');
    expect(TaskStatus::IN_PROGRESS->category())->toBe('ACTIVE');
    expect(TaskStatus::IN_REVIEW->category())->toBe('ACTIVE');
    expect(TaskStatus::BLOCKED->category())->toBe('ACTIVE');
    expect(TaskStatus::DONE->category())->toBe('TERMINAL');
    expect(TaskStatus::CANCELLED->category())->toBe('TERMINAL');
});
```

- [ ] **Step 3: Add the user ownership test.**

Create the test against the public scope names that models must expose. The factory calls intentionally fail until Task 4, while the scope contract remains visible now:

```php
<?php

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Labels\Models\Label;
use App\Domain\Projects\Models\Project;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owned scopes never return another users records', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $ownedProject = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $otherProject = Project::factory()->for($other)->create(['key' => 'OPS']);
    $ownedTask = Task::factory()->forProject($ownedProject)->create(['number' => 1]);
    $otherTask = Task::factory()->forProject($otherProject)->create(['number' => 1]);
    $ownedLabel = Label::factory()->forUser($owner)->create(['normalized_name' => 'owned']);
    $otherLabel = Label::factory()->forUser($other)->create(['normalized_name' => 'owned']);
    $ownedActivity = TaskActivity::factory()->forTask($ownedTask)->create();
    $otherActivity = TaskActivity::factory()->forTask($otherTask)->create();

    expect(Project::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedProject->id]);
    expect(Task::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedTask->id]);
    expect(Label::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedLabel->id]);
    expect(TaskActivity::query()->ownedBy($owner)->pluck('id')->all())
        ->toBe([$ownedActivity->id]);

    expect($owner->projects()->pluck('id')->all())->toBe([$ownedProject->id]);
    expect($owner->tasks()->pluck('id')->all())->toBe([$ownedTask->id]);
    expect($owner->labels()->pluck('id')->all())->toBe([$ownedLabel->id]);
    expect($owner->taskActivities()->pluck('id')->all())->toBe([$ownedActivity->id]);
    expect($otherProject->user_id)->toBe($other->id);
    expect($otherTask->project_id)->toBe($otherProject->id);
    expect($otherLabel->user_id)->toBe($other->id);
    expect($otherActivity->task_id)->toBe($otherTask->id);
});

test('valid activity fixtures retain task and project ownership context', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create(['key' => 'PLAN']);
    $task = Task::factory()->forProject($project)->create(['number' => 1]);
    $activity = TaskActivity::factory()->forTask($task)->create();

    expect($task->user_id)->toBe($owner->id);
    expect($task->project->user_id)->toBe($owner->id);
    expect($activity->user_id)->toBe($owner->id);
    expect($activity->project_id)->toBe($project->id);
    expect($activity->task_id)->toBe($task->id);
});
```

- [ ] **Step 4: Run the red test cycle.**

Run:

```text
php artisan test tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php tests/Feature/Authorization/OwnershipScopeTest.php
```

Expected when PHP is available: FAIL because the new migrations, enums, models, and factories do not exist. If `php --version` or `composer --version` fails first, record the toolchain blocker and do not reinterpret it as a product test failure.

- [ ] **Step 5: Commit the failing contract tests.**

```text
git add tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php tests/Feature/Authorization/OwnershipScopeTest.php
git commit -m "test: define DYX-003 domain invariants"
```

## Task 2: Implement migrations and backed enums

**Files:**

- Modify: `database/migrations/2026_08_21_000000_create_user_preferences_table.php`
- Create: `database/migrations/2026_08_27_000001_create_projects_table.php`
- Create: `database/migrations/2026_08_27_000002_create_tasks_table.php`
- Create: `database/migrations/2026_08_27_000003_create_labels_table.php`
- Create: `database/migrations/2026_08_27_000004_create_task_label_table.php`
- Create: `database/migrations/2026_08_27_000005_create_task_activities_table.php`
- Create: `app/Domain/Projects/Enums/ProjectStatus.php`
- Create: `app/Domain/Tasks/Enums/TaskStatus.php`
- Create: `app/Domain/Tasks/Enums/TaskPriority.php`
- Create: `app/Domain/Activity/Enums/TaskActivityType.php`
- Create: `app/Domain/Identity/Enums/ThemePreference.php`
- Create: `app/Domain/Identity/Enums/DensityPreference.php`
- Create: `app/Domain/Identity/Enums/WeekStartDay.php`

**Interfaces:**

- Consumes: existing `users` and `user_preferences` tables.
- Produces: PostgreSQL-compatible table structure and exact backed enum class names/values consumed by models and future Actions.

- [ ] **Step 1: Align preference timestamps without creating a duplicate table.**

Change only the existing preference migration's `$table->timestamps()` to `$table->timestampsTz()`. Keep `user_id` unique, the foreign key cascading to the user, and the four existing defaults unchanged.

- [ ] **Step 2: Add the projects migration.**

Use this schema shape and explicit index names:

```php
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 160);
    $table->string('key', 10);
    $table->text('description')->nullable();
    $table->string('status', 32)->default('PLANNED');
    $table->string('color', 32)->nullable();
    $table->string('icon', 64)->nullable();
    $table->date('start_on')->nullable();
    $table->date('target_on')->nullable();
    $table->unsignedBigInteger('next_task_number')->default(1);
    $table->timestampTz('archived_at')->nullable();
    $table->timestampsTz();

    $table->unique(['user_id', 'key']);
    $table->index(['user_id', 'status']);
    $table->index(['user_id', 'archived_at']);
    $table->index(['user_id', 'updated_at']);
});
```

- [ ] **Step 3: Add the tasks migration with stable keys and soft deletion.**

Use `restrictOnDelete()` on `project_id` so a physical project delete cannot erase task history, `nullOnDelete()` on `parent_task_id`, `timestampTz` for real timestamps, `date` for `due_on`, and `softDeletesTz()` for `deleted_at`:

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->restrictOnDelete();
    $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
    $table->unsignedBigInteger('number');
    $table->string('title', 300);
    $table->text('description')->nullable();
    $table->string('status', 32)->default('NOT_STARTED');
    $table->string('priority', 32)->default('MEDIUM');
    $table->date('due_on')->nullable();
    $table->unsignedInteger('position')->default(0);
    $table->timestampTz('first_started_at')->nullable();
    $table->timestampTz('completed_at')->nullable();
    $table->timestampTz('cancelled_at')->nullable();
    $table->timestampTz('status_changed_at')->useCurrent();
    $table->timestampsTz();
    $table->softDeletesTz();

    $table->unique(['project_id', 'number']);
    $table->index(['user_id', 'status']);
    $table->index(['project_id', 'status', 'position']);
    $table->index(['project_id', 'parent_task_id']);
    $table->index(['user_id', 'priority']);
    $table->index(['user_id', 'due_on']);
    $table->index(['user_id', 'updated_at']);
    $table->index(['parent_task_id']);
});
```

- [ ] **Step 4: Add labels, pivot, and activity migrations.**

Use these exact relationship and payload rules:

```php
Schema::create('labels', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 80);
    $table->string('normalized_name', 80);
    $table->string('color', 32)->nullable();
    $table->timestampsTz();

    $table->unique(['user_id', 'normalized_name']);
});

Schema::create('task_label', function (Blueprint $table) {
    $table->foreignId('task_id')->constrained()->cascadeOnDelete();
    $table->foreignId('label_id')->constrained()->cascadeOnDelete();
    $table->timestampTz('created_at')->useCurrent();

    $table->unique(['task_id', 'label_id']);
});

Schema::create('task_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->constrained()->restrictOnDelete();
    $table->foreignId('task_id')->constrained()->restrictOnDelete();
    $table->string('event_type', 64);
    $table->string('field', 64)->nullable();
    $table->jsonb('old_value')->nullable();
    $table->jsonb('new_value')->nullable();
    $table->jsonb('metadata')->nullable();
    $table->timestampTz('created_at')->useCurrent();

    $table->index(['user_id', 'created_at']);
    $table->index(['project_id', 'created_at']);
    $table->index(['task_id', 'created_at']);
    $table->index(['event_type', 'created_at']);
});
```

The pivot cascades only association rows. Task and activity history is retained through normal soft deletion and project archiving; no application flow physically deletes those rows in this slice.

- [ ] **Step 5: Add all backed enums with exact persisted values.**

Implement the simple cases as string-backed enums. `TaskStatus` must include the category method exactly as follows:

```php
namespace App\Domain\Tasks\Enums;

enum TaskStatus: string
{
    case BACKLOG = 'BACKLOG';
    case NOT_STARTED = 'NOT_STARTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case IN_REVIEW = 'IN_REVIEW';
    case BLOCKED = 'BLOCKED';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';

    public function category(): string
    {
        return match ($this) {
            self::BACKLOG, self::NOT_STARTED => 'PLANNED',
            self::IN_PROGRESS, self::IN_REVIEW, self::BLOCKED => 'ACTIVE',
            self::DONE, self::CANCELLED => 'TERMINAL',
        };
    }
}
```

Use these cases for the remaining enums:

```text
ProjectStatus: PLANNED, ACTIVE, ON_HOLD, COMPLETED, CANCELLED
TaskPriority: LOW, MEDIUM, HIGH, URGENT
TaskActivityType: TASK_CREATED, TASK_UPDATED, STATUS_CHANGED, PRIORITY_CHANGED,
                  DUE_DATE_CHANGED, LABEL_ADDED, LABEL_REMOVED, SUBTASK_CREATED,
                  TASK_MOVED_PROJECT, TASK_DELETED, TASK_RESTORED
ThemePreference: SYSTEM, LIGHT, DARK
DensityPreference: COMFORTABLE, COMPACT
WeekStartDay: MONDAY, SUNDAY
```

- [ ] **Step 6: Run the migration and schema/status tests.**

Run:

```text
php artisan migrate:fresh
php artisan test tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php
```

Expected: migrations complete and the schema/status tests pass. The ownership test remains pending until factories exist. If the environment still reports that `php` is not recognized, stop and report that exact toolchain issue.

- [ ] **Step 7: Commit persistence and enum work.**

```text
git add database/migrations app/Domain/Projects/Enums app/Domain/Tasks/Enums app/Domain/Activity/Enums app/Domain/Identity/Enums
git commit -m "feat: add PlanOps domain schema and enums"
```

## Task 3: Implement models, casts, relationships, and ownership scopes

**Files:**

- Modify: `app/Models/User.php`
- Modify: `app/Domain/Identity/Models/UserPreference.php`
- Create: `app/Domain/Projects/Models/Project.php`
- Create: `app/Domain/Tasks/Models/Task.php`
- Create: `app/Domain/Labels/Models/Label.php`
- Create: `app/Domain/Activity/Models/TaskActivity.php`

**Interfaces:**

- Consumes: tables and enums from Task 2.
- Produces: `User::projects()`, `User::tasks()`, `User::labels()`, `User::taskActivities()`, the domain relationships, and `ownedBy(User|int $owner)` scopes used by queries and tests.

- [ ] **Step 1: Extend `User` with typed ownership relationships.**

Import `HasMany` and the four domain models. Add these methods without changing authentication behaviour:

```php
public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}

public function tasks(): HasMany
{
    return $this->hasMany(Task::class);
}

public function labels(): HasMany
{
    return $this->hasMany(Label::class);
}

public function taskActivities(): HasMany
{
    return $this->hasMany(TaskActivity::class);
}
```

- [ ] **Step 2: Add enum casts to `UserPreference`.**

Keep `timezone` as a string and cast the other fields to `WeekStartDay`, `ThemePreference`, and `DensityPreference`. Preserve the existing default attributes so a newly created preference row still renders the four documented defaults.

- [ ] **Step 3: Implement the `Project` model.**

The model must have fillable project fields, a `status` cast to `ProjectStatus`, immutable date casts for `start_on` and `target_on`, a `belongsTo(User::class)` relation, `hasMany(Task::class)` and `hasMany(TaskActivity::class)` relations, and an explicit scope:

```php
public function scopeOwnedBy(Builder $query, User|int $owner): Builder
{
    $ownerId = $owner instanceof User ? $owner->getKey() : $owner;

    return $query->where($query->getModel()->qualifyColumn('user_id'), $ownerId);
}
```

Use the same scope body in each user-owned domain model rather than hiding ownership in a repository abstraction.

- [ ] **Step 4: Implement the `Task` model and hierarchy relationships.**

Use `SoftDeletes`, fillable task fields, casts for `TaskStatus`, `TaskPriority`, immutable `due_on`, immutable lifecycle timestamps, and these relations:

```php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

public function project(): BelongsTo
{
    return $this->belongsTo(Project::class);
}

public function parent(): BelongsTo
{
    return $this->belongsTo(self::class, 'parent_task_id');
}

public function children(): HasMany
{
    return $this->hasMany(self::class, 'parent_task_id');
}

public function labels(): BelongsToMany
{
    return $this->belongsToMany(Label::class, 'task_label')->withTimestamps();
}

public function activities(): HasMany
{
    return $this->hasMany(TaskActivity::class);
}
```

Do not add task-number allocation or mutation Actions here. The model only exposes the persistence relationships and `ownedBy()` scope needed by later work.

- [ ] **Step 5: Implement `Label` and `TaskActivity`.**

`Label` belongs to `User`, belongs to many `Task` records through `task_label` with pivot timestamps, and casts no enum. `TaskActivity` belongs to `User`, `Project`, and `Task`; casts `event_type` to `TaskActivityType`; and casts `old_value`, `new_value`, and `metadata` to arrays.

Use explicit fillable fields for `TaskActivity` so the future recorder can create rows while no update/delete Action is exposed:

```php
protected $fillable = [
    'user_id',
    'project_id',
    'task_id',
    'event_type',
    'field',
    'old_value',
    'new_value',
    'metadata',
];
```

The model is append-only by application convention in this slice. Recorder-level normalization and stronger mutation protection are DYX-004 concerns.

- [ ] **Step 6: Run model autoload and focused tests.**

Run:

```text
composer dump-autoload
php artisan test tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php
```

Expected: PASS. The ownership test is allowed to remain red until Task 4 supplies its factories.

- [ ] **Step 7: Commit model and relationship work.**

```text
git add app/Models/User.php app/Domain/Identity/Models/UserPreference.php app/Domain/Projects/Models/Project.php app/Domain/Tasks/Models/Task.php app/Domain/Labels/Models/Label.php app/Domain/Activity/Models/TaskActivity.php
git commit -m "feat: add PlanOps domain relationships"
```

## Task 4: Add deterministic factories and seed coverage

**Files:**

- Create: `database/factories/UserPreferenceFactory.php`
- Create: `database/factories/ProjectFactory.php`
- Create: `database/factories/TaskFactory.php`
- Create: `database/factories/LabelFactory.php`
- Create: `database/factories/TaskActivityFactory.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**

- Consumes: model relationships, enum casts, and scopes from Tasks 2–3.
- Produces: valid factory states `forUser`, `forProject`, `withParent`, one state per lifecycle/status value, `deleted`, `reopened`, and a reproducible `DatabaseSeeder` dataset.

- [ ] **Step 1: Implement `UserPreferenceFactory`.**

Default to a new user and the four documented preference values. Add states named `timezone(string $timezone)`, `sundayStart()`, `light()`, `dark()`, and `compact()` so tests and seed fixtures can vary preferences without manually duplicating arrays.

- [ ] **Step 2: Implement `ProjectFactory` with lifecycle states.**

Default fields must include a valid uppercase key, a real user relation, `ProjectStatus::PLANNED`, and `next_task_number` equal to `1`. Add these state methods returning `static`: `planned()`, `active()`, `onHold()`, `completed()`, and `cancelled()`. Use explicit keys in the seeder and ownership tests; generated keys only need to satisfy `^[A-Z0-9]{2,10}$`.

- [ ] **Step 3: Implement `TaskFactory` with valid project ownership.**

Default to a project relation, derive `user_id` from that project, use `TaskStatus::NOT_STARTED`, `TaskPriority::MEDIUM`, position `0`, and a non-negative number. Add these states:

```php
public function forProject(Project $project): static;
public function withParent(Task $parent): static;
public function backlog(): static;
public function active(): static;
public function inReview(): static;
public function blocked(): static;
public function done(): static;
public function cancelled(): static;
public function deleted(): static;
public function reopened(): static;
```

`forProject()` must set both `project_id` and `user_id`; `withParent()` must set `parent_task_id`, `project_id`, and `user_id` from the parent. The `deleted()` state sets `deleted_at` to a deterministic past timestamp. The `reopened()` state uses a non-terminal status, clears current terminal timestamps, retains `first_started_at`, and sets `status_changed_at` to a recent timestamp.

- [ ] **Step 4: Implement `LabelFactory` and `TaskActivityFactory`.**

`LabelFactory` defaults to a user relation, a display name, its normalized lowercase name, and a nullable color. Add `forUser(User $user)`.

`TaskActivityFactory` defaults to a task relation, derives `user_id` and `project_id` from the task, uses `TaskActivityType::TASK_UPDATED`, and supplies nullable payload arrays. Add `forTask(Task $task)` and one named state per activity type only where a seed/test needs a readable fixture; the `event_type` field must always receive the backed enum or its stable string value.

- [ ] **Step 5: Replace the tutorial-only seeder with a deterministic PlanOps fixture.**

Keep `WithoutModelEvents`. Create one primary user (`test@example.com`) with Casablanca preferences and one second user with a different timezone. Create five projects with explicit keys and lifecycle states `PLANNED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, and `CANCELLED`. Create top-level tasks, a one-level subtask, labels, status activity rows, one soft-deleted task, and one reopened-task fixture with historical `STATUS_CHANGED` activity.

The seed sequence must use explicit numbers to remain reproducible:

```php
$owner = User::factory()->create([
    'name' => 'Test User',
    'email' => 'test@example.com',
]);

$owner->preference()->create([
    'timezone' => 'Africa/Casablanca',
    'week_start_day' => 'MONDAY',
    'theme' => 'SYSTEM',
    'density' => 'COMFORTABLE',
]);

$activeProject = Project::factory()->for($owner)->active()->create([
    'name' => 'PlanOps Core',
    'key' => 'PLAN',
]);

$doneTask = Task::factory()->forProject($activeProject)->done()->create([
    'number' => 1,
    'title' => 'Model the core domain',
]);

$subtask = Task::factory()->forProject($activeProject)->withParent($doneTask)->done()->create([
    'number' => 2,
    'title' => 'Verify the schema contract',
]);

$reopenedTask = Task::factory()->forProject($activeProject)->reopened()->create([
    'number' => 3,
    'title' => 'Review the persistence foundation',
]);

TaskActivity::factory()->forTask($doneTask)->create([
    'event_type' => TaskActivityType::STATUS_CHANGED,
    'old_value' => ['status' => 'IN_PROGRESS'],
    'new_value' => ['status' => 'DONE'],
]);

TaskActivity::factory()->forTask($reopenedTask)->create([
    'event_type' => TaskActivityType::STATUS_CHANGED,
    'old_value' => ['status' => 'DONE'],
    'new_value' => ['status' => 'IN_PROGRESS'],
    'metadata' => ['is_reopen' => true],
]);
```

Use the same explicit pattern for the remaining lifecycle projects and a deleted task. Do not seed deferred concepts or call future mutation Actions from the seeder.

- [ ] **Step 6: Run the complete DYX-003 green cycle.**

Run:

```text
php artisan migrate:fresh --seed
php artisan test tests/Feature/Database tests/Unit/Domain/Tasks/TaskStatusTest.php tests/Feature/Authorization/OwnershipScopeTest.php
```

Expected: the database rebuilds reproducibly, all DYX-003 tests pass, both users retain separate data, task/activity context is internally consistent, and deleted/reopened fixtures remain queryable in explicit history contexts.

- [ ] **Step 7: Commit factories and seed coverage.**

```text
git add app database tests
git commit -m "feat: add deterministic PlanOps domain fixtures"
```

## Task 5: Final DYX-003 verification and handoff

**Files:**

- Modify: `docs/superpowers/plans/2026-08-20-planops-implementation.md` only to check off DYX-003 after all evidence is green.

**Interfaces:**

- Consumes: all DYX-003 code and tests.
- Produces: a verified foundation ready for DYX-004 activity recording and DYX-005 project lifecycle Actions.

- [ ] **Step 1: Run the full application test suite.**

Run:

```text
php artisan config:clear
php artisan test
```

Expected: existing DYX-002 authentication, shell, settings, and profile tests remain green alongside all DYX-003 tests.

- [ ] **Step 2: Build the existing frontend asset pipeline.**

Run:

```text
npm run build
```

Expected: the existing Vite build completes without frontend changes introduced by the domain foundation.

- [ ] **Step 3: Inspect the final diff and repository state.**

Run:

```text
git diff --check
git status --short
git log --oneline --decorate -6
```

Expected: no whitespace errors, only the intended DYX-003 files changed, and the four logical DYX-003 commits are visible after the design and scaffold commits.

- [ ] **Step 4: Update the master implementation plan after evidence is recorded.**

Check only the DYX-003 task and its completed steps in `docs/superpowers/plans/2026-08-20-planops-implementation.md`; do not mark DYX-004 or any dependent task complete.

- [ ] **Step 5: Report the handoff.**

Include the commit hashes, test/build commands and results, the PHP/Composer toolchain status, and the next unblocked slice: DYX-004 append-only activity recording.

## Self-review checklist

- [x] The plan covers all seven tables, including the already-existing preferences migration.
- [x] Every enum and persisted value from the DYX-003 design is assigned to a concrete file/task.
- [x] Unique keys, soft deletion, JSON payload columns, and required indexes have executable test coverage.
- [x] Ownership scopes and inverse relations are named consistently as `ownedBy`, `projects`, `tasks`, `labels`, and `taskActivities`.
- [x] Same-owner parentage is explicitly kept out of the schema-only slice and reserved for later mutation rules.
- [x] Task numbering is represented in the schema but allocation remains outside the model/factory foundation.
- [x] Factory states cover lifecycle/status variation, deleted tasks, reopened tasks, labels, activities, and user timezones.
- [x] The plan contains no placeholder implementation steps or unspecified error-handling tasks.
- [x] Verification includes migration reproducibility, focused tests, the full suite, frontend build, whitespace checks, and commit inspection.
