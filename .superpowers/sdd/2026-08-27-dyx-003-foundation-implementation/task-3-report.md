# DYX-003 Task 3 Report: Models, Casts, Relationships, and Ownership Scopes

## Status

Implemented Task 3 model-layer work and committed it. Runtime Laravel verification could not start because PHP and Composer are unavailable on PATH.

## Implementation

- Extended `App\Models\User` with `projects()`, `tasks()`, `labels()`, and `taskActivities()` `HasMany` relationships without changing its authentication casts, fillable attributes, hidden attributes, factory usage, or preference relationship.
- Preserved `UserPreference` defaults exactly (`Africa/Casablanca`, `MONDAY`, `SYSTEM`, `COMFORTABLE`) and added explicit casts: `timezone` as string; `week_start_day`, `theme`, and `density` as their backed enums.
- Added `Project`, `Task`, `Label`, and `TaskActivity` models with the requested fillable fields, relationships, casts, and ownership query scopes.
- Added the public `ownedBy(User|int $owner)` scope with the specified qualified `user_id` predicate to all user-owned models: `UserPreference`, `Project`, `Task`, `Label`, and `TaskActivity`.
- Applied `SoftDeletes` to `Task`; added immutable date/lifecycle casts; and set `TaskActivity::$timestamps` to `false` because its schema only owns `created_at` (no `updated_at`).
- Added no mutation Actions, policies, routes, allocation logic, activity-recording behavior, factories, or frontend changes.

## Files Changed

- Modified: `app/Models/User.php`
- Modified: `app/Domain/Identity/Models/UserPreference.php`
- Created: `app/Domain/Projects/Models/Project.php`
- Created: `app/Domain/Tasks/Models/Task.php`
- Created: `app/Domain/Labels/Models/Label.php`
- Created: `app/Domain/Activity/Models/TaskActivity.php`

## Tests and Commands

The task-required focused commands were attempted after the final source changes:

```text
composer dump-autoload
php artisan test tests/Feature/Database/SchemaInvariantTest.php tests/Unit/Domain/Tasks/TaskStatusTest.php
```

Output:

```text
composer : Le terme «composer» n'est pas reconnu comme nom d'applet de commande, fonction, fichier de script ou
programme exécutable. Vérifiez l'orthographe du nom, ou si un chemin d'accès existe, vérifiez que le chemin d'accès
est correct et réessayez.
Au caractère Ligne:2 : 1
+ composer dump-autoload
+ ~~~~~~~~
    + CategoryInfo          : ObjectNotFound: (composer:String) [], CommandNotFoundException
    + FullyQualifiedErrorId : CommandNotFoundException

php : Le terme «php» n'est pas reconnu comme nom d'applet de commande, fonction, fichier de script ou programme
exécutable. Vérifiez l'orthographe du nom, ou si un chemin d'accès existe, vérifiez que le chemin d'accès est correct
et réessayez.
Au caractère Ligne:4 : 1
+ php artisan test tests/Feature/Database/SchemaInvariantTest.php tests ...
+ ~~~
    + CategoryInfo          : ObjectNotFound: (php:String) [], CommandNotFoundException
    + FullyQualifiedErrorId : CommandNotFoundException
```

Result: neither command reached Composer, Artisan, or PHPUnit/Pest. Laravel tests did not run and are not claimed as passing.

Static verification run:

```text
git diff --check
```

Result: exit code 0 with no whitespace errors.

## TDD Note

Task 3's supplied plan does not add a model test file. Its specified runnable target is Task 1's schema/status suite, while ownership behavior is intentionally deferred until Task 4 supplies factories. The prescribed command was attempted before and after implementation, but command discovery failed in both cases because `composer` and `php` are not on PATH. No runtime red/green cycle could therefore be observed.

## Self-Review

- Re-read all six changed model files after editing.
- Confirmed each required User relationship, Project relationship, Task hierarchy/pivot/activity relationship, Label pivot relationship, and TaskActivity relationship is present.
- Confirmed enum class imports and casts align with the Task 2 enum namespaces and backed values.
- Confirmed date-only columns use `immutable_date` and Task lifecycle timestamps use `immutable_datetime`.
- Confirmed every user-owned model exposes the specified scope body using the model-qualified `user_id` column.
- Confirmed preference defaults and User authentication behavior remain intact.
- Confirmed the diff has no whitespace errors with `git diff --check`.

## Concerns

- PHP and Composer are unavailable on PATH, so autoloading, syntax parsing by PHP, migrations, and the focused Laravel tests remain unverified in this environment.
- The factory-backed ownership test remains intentionally deferred to Task 4, as directed.

## Fix Round 1: Retain Soft-Deleted Task Relationships

### Review Finding

`TaskActivity::task()` used the default `BelongsTo` relation. Because `Task` uses `SoftDeletes`, that relation excluded a soft-deleted task even though the activity row remained available. The relation now uses the requested `withTrashed()` behavior:

```php
return $this->belongsTo(Task::class)->withTrashed();
```

### Regression Test

Added `activity history retains its task relationship after soft deletion` to `tests/Feature/Authorization/OwnershipScopeTest.php`. The test creates a user, project, task, and activity directly using the existing model contracts; soft-deletes the task; reloads the activity; and asserts that its task relationship is present, points to the original task, and remains trashed. No Task 4 factories were added.

### Covering Test Command and Output

The required command was attempted before the model fix (TDD red-phase attempt):

```text
php artisan test tests/Feature/Authorization/OwnershipScopeTest.php
```

Exact output:

```text
php : Le terme «php» n'est pas reconnu comme nom d'applet de commande, fonction, fichier de script ou programme
exécutable. Vérifiez l'orthographe du nom, ou si un chemin d'accès existe, vérifiez que le chemin d'accès est correct
et réessayez.
Au caractère Ligne:2 : 1
+ php artisan test tests/Feature/Authorization/OwnershipScopeTest.php
+ ~~~
    + CategoryInfo          : ObjectNotFound: (php:String) [], CommandNotFoundException
    + FullyQualifiedErrorId : CommandNotFoundException
```

The same required command was attempted after the model fix:

```text
php artisan test tests/Feature/Authorization/OwnershipScopeTest.php
```

Exact output:

```text
php : Le terme «php» n'est pas reconnu comme nom d'applet de commande, fonction, fichier ou programme exécutable. Vérifiez
l'orthographe du nom, ou si un chemin d'accès existe, vérifiez que le chemin d'accès est correct
et réessayez.
Au caractère Ligne:2 : 1
+ php artisan test tests/Feature/Authorization/OwnershipScopeTest.php
+ ~~~
    + CategoryInfo          : ObjectNotFound: (php:String) [], CommandNotFoundException
    + FullyQualifiedErrorId : CommandNotFoundException
```

Neither attempt reached Artisan or the test runner. The regression test therefore could not be observed passing in this environment and is not claimed as passed.

### Fix Verification and Concerns

- Re-read the changed `TaskActivity::task()` relation and the new regression test.
- Confirmed the fix is limited to `withTrashed()` and the requested test.
- Confirmed no Task 4 factories or unrelated production behavior were introduced.
- `git diff --check` remains the available static check; PHP runtime verification is blocked because `php` is unavailable on PATH.
