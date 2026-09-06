# DYX-006 Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver safe, recipient-scoped invitation and assignment notifications after committed collaboration outcomes.

**Architecture:** Use Laravel database notifications and the existing database queue. Collaboration actions publish small redacted outcome objects after their transactions; a queued job persists/delivers them idempotently and rechecks authorization before creating target links. A notification-center controller reads only the authenticated recipient’s records.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, database queue, Pest 4, Blade, SQLite feature tests with isolated PostgreSQL concurrency checks where row locks matter.

**Spec:** `docs/superpowers/specs/2026-09-06-dyx-006-notifications-design.md`

## Global Constraints

- Notifications describe committed outcomes only; rolled-back actions emit nothing.
- Raw invitation tokens and credentials never enter notification payloads or logs.
- Existing membership, task, and project policies remain canonical.
- Removed/deactivated recipients and inaccessible targets receive no actionable link.
- WebSockets, push delivery, and unrelated-member fan-out remain out of scope.
- The full legacy suite remains a separate baseline gate because it has unrelated pre-existing failures.

---

### Task 1: Define notification outcome contracts

**Files:**
- Create: `app/Domain/Notifications/Enums/NotificationEventType.php`
- Create: `app/Domain/Notifications/Data/NotificationOutcome.php`
- Create: `tests/Feature/Notifications/NotificationOutcomeTest.php`

**Interfaces:**
- Produces `NotificationOutcome::invitationCreated(int $invitationId, int $projectId, int $recipientId, string $projectName): self`.
- Produces `NotificationOutcome::assigneeChanged(int $taskId, int $projectId, int $recipientId, ?int $oldAssigneeId, int $newAssigneeId, int $actorId, string $taskTitle): self`.
- Produces `NotificationOutcome::idempotencyKey(): string` and `toPayload(): array`.

- [ ] **Step 1: Write the failing contract tests**

Assert exact event names, recipient/project/target IDs, deterministic keys, and that invitation payloads contain no `token` or raw invitation URL.

```php
it('builds redacted stable invitation and assignment outcomes', function (): void {
    $invitation = NotificationOutcome::invitationCreated(8, 3, 21, 'Launch');
    $assignment = NotificationOutcome::assigneeChanged(55, 3, 21, null, 21, 9, 'Prepare brief');

    expect($invitation->eventType)->toBe(NotificationEventType::INVITATION_CREATED)
        ->and($invitation->recipientId)->toBe(21)
        ->and($invitation->idempotencyKey())->toBe('INVITATION_CREATED:8:21')
        ->and($invitation->toPayload())->not->toHaveKey('token')
        ->and($assignment->eventType)->toBe(NotificationEventType::ASSIGNEE_CHANGED)
        ->and($assignment->idempotencyKey())->toBe('ASSIGNEE_CHANGED:55:21:21')
        ->and($assignment->toPayload()['task_title'])->toBe('Prepare brief');
});
```

- [ ] **Step 2: Run the contract test and verify the expected failure**

Run: `php artisan test tests/Feature/Notifications/NotificationOutcomeTest.php --no-ansi`

Expected: FAIL because the enum and value object do not exist.

- [ ] **Step 3: Implement the enum and immutable value object**

Use a readonly class with public typed properties. Keep payload keys scalar and redacted. Make the assignment key include task ID and recipient ID so reassignment to the same person cannot duplicate a committed outcome.

- [ ] **Step 4: Run the contract test and verify it passes**

Run: `php artisan test tests/Feature/Notifications/NotificationOutcomeTest.php --no-ansi`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
git add app/Domain/Notifications tests/Feature/Notifications/NotificationOutcomeTest.php
git commit -m "test: define notification outcome contracts"
```

### Task 2: Persist notifications with idempotency and safe target resolution

**Files:**
- Create: `database/migrations/2026_09_06_000002_create_planops_notifications_table.php`
- Create: `app/Domain/Notifications/Models/PlanOpsNotification.php`
- Create: `app/Domain/Notifications/Actions/PersistNotificationOutcome.php`
- Create: `app/Domain/Notifications/Queries/NotificationCenterQuery.php`
- Create: `tests/Feature/Notifications/NotificationPersistenceTest.php`

**Interfaces:**
- `PersistNotificationOutcome::handle(NotificationOutcome $outcome): ?PlanOpsNotification`.
- `NotificationCenterQuery::for(User $recipient): Builder`.
- The table stores `recipient_id`, `event_type`, `idempotency_key`, `project_id`, nullable `target_type`, nullable `target_id`, JSON `data`, nullable `read_at`, and timestamps with a unique `idempotency_key`.

- [ ] **Step 1: Write failing persistence tests**

Cover first insert, duplicate suppression, recipient isolation, and removal of unsafe target links when the project/task is no longer accessible.

```php
it('persists one recipient-scoped row per idempotency key', function (): void {
    $user = User::factory()->create();
    $outcome = NotificationOutcome::invitationCreated(8, 3, $user->id, 'Launch');

    $first = (new PersistNotificationOutcome)->handle($outcome);
    $second = (new PersistNotificationOutcome)->handle($outcome);

    expect($first->id)->toBe($second->id)
        ->and(PlanOpsNotification::query()->where('recipient_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run the persistence tests and verify the expected failure**

Run: `php artisan test tests/Feature/Notifications/NotificationPersistenceTest.php --no-ansi`

Expected: FAIL because the migration, model, and action do not exist.

- [ ] **Step 3: Implement migration, model, action, and recipient query**

Use a unique idempotency key, JSON casting, `read_at` nullable timestamp, and an Eloquent scope/query that always starts with `where('recipient_id', $recipient->id)`. Use `firstOrCreate` inside a transaction and never store raw invitation tokens.

- [ ] **Step 4: Run persistence tests and verify they pass**

Run: `php artisan test tests/Feature/Notifications/NotificationPersistenceTest.php --no-ansi`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
git add database/migrations app/Domain/Notifications tests/Feature/Notifications/NotificationPersistenceTest.php
git commit -m "feat: persist idempotent notifications"
```

### Task 3: Emit invitation and assignment outcomes after commit

**Files:**
- Create: `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php`
- Create: `app/Domain/Notifications/Listeners/QueueNotificationOutcome.php`
- Modify: `app/Domain/Collaboration/Actions/InviteProjectMember.php`
- Modify: `app/Domain/Tasks/Actions/AssignTask.php`
- Modify: `config/queue.php`
- Create: `tests/Feature/Notifications/AfterCommitNotificationTest.php`

**Interfaces:**
- `DeliverNotificationOutcome` implements `ShouldQueue`, has `$tries = 3`, a finite `backoff(): array`, and accepts `NotificationOutcome` in its constructor.
- `QueueNotificationOutcome::handle(NotificationOutcome $outcome): void` dispatches `DeliverNotificationOutcome` after the current transaction commits.

- [ ] **Step 1: Write failing transaction tests**

Fake the queue and assert no job is visible before commit, no job is released after rollback, and a delivery exception does not undo a committed invitation or assignment.

```php
it('releases assignment notification only after commit', function (): void {
    Queue::fake();
    // Create owner/member/project/task and begin a transaction.
    // Call AssignTask, assert Queue::assertNothingPushed(), commit,
    // then assert a DeliverNotificationOutcome job was pushed.
});
```

- [ ] **Step 2: Run the transaction tests and verify the expected failure**

Run: `php artisan test tests/Feature/Notifications/AfterCommitNotificationTest.php --no-ansi`

Expected: FAIL because collaboration actions do not dispatch notification outcomes.

- [ ] **Step 3: Implement after-commit dispatch**

Register the queued job through Laravel’s after-commit dispatch mechanism. Invitation outcomes target the invited user only after the invitation row exists. Assignment outcomes target the new assignee only after the task update and activity row succeed. Configure the database queue connection for after-commit release.

- [ ] **Step 4: Run transaction and collaboration tests**

Run: `php artisan test tests/Feature/Notifications/AfterCommitNotificationTest.php tests/Feature/Collaboration/InvitationLifecycleTest.php tests/Feature/Collaboration/AssignmentTest.php --no-ansi`

Expected: PASS, with business rows preserved if delivery is forced to fail.

- [ ] **Step 5: Commit**

```text
git add app/Domain/Collaboration/Actions/InviteProjectMember.php app/Domain/Tasks/Actions/AssignTask.php app/Domain/Notifications config/queue.php tests/Feature/Notifications/AfterCommitNotificationTest.php
git commit -m "feat: queue collaboration notifications after commit"
```

### Task 4: Add retry, reauthorization, and failure observability

**Files:**
- Create: `app/Domain/Notifications/Models/NotificationDeliveryFailure.php`
- Create: `database/migrations/2026_09_06_000003_create_notification_delivery_failures_table.php`
- Modify: `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php`
- Create: `tests/Feature/Notifications/NotificationDeliveryTest.php`

**Interfaces:**
- `DeliverNotificationOutcome::handle(PersistNotificationOutcome $persist): void` suppresses unauthorized or deleted targets without throwing.
- `DeliverNotificationOutcome::failed(Throwable $exception): void` writes sanitized failure metadata.

- [ ] **Step 1: Write failing delivery tests**

Test duplicate jobs, three-attempt bound, removed recipient, revoked invitation, archived project, deleted task, and failure rows without token strings.

- [ ] **Step 2: Run the delivery tests and verify the expected failures**

Run: `php artisan test tests/Feature/Notifications/NotificationDeliveryTest.php --no-ansi`

Expected: FAIL because retry and target reauthorization are not implemented.

- [ ] **Step 3: Implement bounded retry and sanitized failure logging**

Re-fetch recipient, project, invitation, or task through canonical accessible queries. Set `target_type`/`target_id` to null when the target is no longer safe. Persist only event type, numeric IDs, attempt count, exception class, and a bounded sanitized message.

- [ ] **Step 4: Run delivery tests and verify they pass**

Run: `php artisan test tests/Feature/Notifications/NotificationDeliveryTest.php --no-ansi`

Expected: PASS.

- [ ] **Step 5: Commit**

```text
git add app/Domain/Notifications database/migrations tests/Feature/Notifications/NotificationDeliveryTest.php
git commit -m "test: verify notification retries and reauthorization"
```

### Task 5: Build the recipient-scoped notification center

**Files:**
- Create: `app/Http/Controllers/NotificationController.php`
- Create: `app/Http/Requests/NotificationReadRequest.php`
- Modify: `routes/web.php`
- Create: `resources/views/pages/notifications/index.blade.php`
- Modify: `resources/views/layouts/navigation.blade.php`
- Create: `tests/Feature/Notifications/NotificationCenterTest.php`

**Interfaces:**
- `GET /notifications` named `notifications.index`.
- `PATCH /notifications/{notification}/read` named `notifications.read`.
- `PATCH /notifications/read-all` named `notifications.read-all`.

- [ ] **Step 1: Write failing request/UI tests**

Assert unread count, recipient isolation, mark-read, mark-all-read, duplicate requests, safe target links, keyboard labels, and a live status region.

- [ ] **Step 2: Run the notification-center tests and verify failure**

Run: `php artisan test tests/Feature/Notifications/NotificationCenterTest.php --no-ansi`

Expected: FAIL because routes, controller, and view do not exist.

- [ ] **Step 3: Implement scoped routes, controller, view, and navigation bell**

Authorize every mutation through a recipient-scoped lookup. Render target links only when the target remains accessible; otherwise render plain message text. Use standard form controls with visible labels and `role="status"` feedback.

- [ ] **Step 4: Run center tests and view cache**

Run: `php artisan test tests/Feature/Notifications/NotificationCenterTest.php --no-ansi`

Run: `php artisan view:cache`

Expected: PASS and successful Blade cache generation.

- [ ] **Step 5: Commit**

```text
git add app/Http/Controllers/NotificationController.php app/Http/Requests/NotificationReadRequest.php routes/web.php resources/views resources/views/layouts/navigation.blade.php tests/Feature/Notifications/NotificationCenterTest.php
git commit -m "feat: add recipient notification center"
```

### Task 6: Final Sprint 2 verification

**Files:**
- Modify: `docs/backlogs/DYX-006-notifications.md`
- Create: `docs/reports/2026-09-06-dyx-006-verification.md`

- [ ] **Step 1: Run focused notification and collaboration suites**

Run: `php artisan test tests/Feature/Notifications tests/Feature/Collaboration --no-ansi`

- [ ] **Step 2: Run static and frontend checks**

Run: `git diff --check`

Run: `php artisan view:cache`

Run: `npm.cmd run build`

- [ ] **Step 3: Record evidence and remaining baseline failures**

Document exact test counts, skipped PostgreSQL-only checks, build output, and any unrelated legacy failures. Do not mark the release gate green unless DYX-005 and DYX-006 evidence is complete.

- [ ] **Step 4: Commit verification evidence**

```text
git add docs/backlogs/DYX-006-notifications.md docs/reports/2026-09-06-dyx-006-verification.md
git commit -m "docs: record DYX-006 verification"
```
