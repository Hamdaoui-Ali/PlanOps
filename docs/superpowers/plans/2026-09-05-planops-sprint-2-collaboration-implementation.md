# PlanOps 1.1 Collaboration Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move PlanOps from user-owned projects to a secure project-scoped collaboration model with invitations, roles, one assignee per task, actor-aware history, and assignment-based My Work.

**Architecture:** Keep the current Laravel domain boundaries. Add active project memberships as the access boundary, explicit project/task/label policies, additive PostgreSQL migrations, transaction-locked collaboration actions, and after-commit notification events. Ship the P0 security and assignment slice before P1 notification delivery and reporting polish.

**Tech Stack:** PHP 8.3+, Laravel 13, Livewire 4, Blade, Tailwind, PostgreSQL, Pest 4, Vite, Playwright, axe-core.

**Spec:** docs/PlanOps_Sprint_2.md

## Global Constraints

- An active membership has removed_at IS NULL.
- Every project has exactly one active OWNER membership, and projects.owner_id matches it.
- Roles are project-scoped: OWNER, ADMIN, MEMBER.
- Project keys are globally unique.
- Invitations use canonical lower-case email, normalized_email, a hashed token, and one pending row per project/email.
- Invitation acceptance is authenticated, CSRF-protected, rate-limited, and atomic.
- A task has zero or one assignee, and an assignee must be an active member of the task project.
- Members may change non-CANCELLED status only for tasks assigned to them.
- Archived/cancelled projects are read-only except for Owner/Admin restore.
- Every task mutation records the authenticated actor; membership/security changes append project_events.
- Every resource query and export is scoped before rows are loaded.
- Notification delivery occurs after commit and never determines whether the business transaction succeeds.
- PostgreSQL is the authority for partial indexes and concurrency verification.
- No hard-delete account action may destroy creator, assignee, actor, or project history.
- No implementation is complete with unapproved test failures or stale architecture/UI contracts.

---

## File map

### Create

- app/Domain/Collaboration/Enums/ProjectRole.php — project-scoped OWNER/ADMIN/MEMBER enum.
- app/Domain/Collaboration/Models/ProjectMembership.php — active/removed membership row and relations.
- app/Domain/Collaboration/Models/ProjectInvitation.php — normalized invitation and derived lifecycle.
- app/Domain/Collaboration/Models/ProjectEvent.php — immutable project security/audit event.
- app/Domain/Collaboration/Enums/ProjectEventType.php — finite event types.
- app/Domain/Collaboration/Actions/InviteProjectMember.php — authorized invite and resend state.
- app/Domain/Collaboration/Actions/AcceptProjectInvitation.php — atomic accept.
- app/Domain/Collaboration/Actions/RevokeProjectInvitation.php — revoke pending invite.
- app/Domain/Collaboration/Actions/ResendProjectInvitation.php — rotate token and expiry.
- app/Domain/Collaboration/Actions/RemoveProjectMember.php — deactivate member and unassign tasks.
- app/Domain/Collaboration/Actions/ChangeProjectMemberRole.php — Owner-only role changes.
- app/Domain/Collaboration/Actions/TransferProjectOwnership.php — atomic Owner transfer.
- app/Domain/Tasks/Actions/AssignTask.php — assign/reassign/unassign one task.
- app/Http/Controllers/Collaboration/ProjectTeamController.php — Team read surface.
- app/Http/Controllers/Collaboration/ProjectInvitationController.php — invite/revoke/resend.
- app/Http/Controllers/Collaboration/ProjectMembershipController.php — remove/role changes.
- app/Http/Controllers/Collaboration/ProjectOwnershipController.php — transfer endpoint.
- app/Http/Controllers/InvitationAcceptanceController.php — public preview/authenticated accept.
- app/Http/Controllers/NotificationController.php — notification list/read actions.
- app/Notifications/ProjectInvitationCreated.php — P1 invitation notification.
- app/Notifications/TaskAssigned.php — P1 assignment notification.
- tests/Feature/Collaboration/ — membership, invitation, access, assignment, concurrency, and migration tests.
- tests/Feature/Notifications/ — after-commit and read-state tests.
- tests/Browser/Collaboration/ — invite, assignment, keyboard, and forbidden-action journeys.
- playwright.config.ts — PostgreSQL-backed browser test configuration.
- tests/Browser/axe.setup.ts — axe-core accessibility setup.

### Modify

- app/Domain/Projects/Models/Project.php
- app/Domain/Tasks/Models/Task.php
- app/Domain/Labels/Models/Label.php
- app/Domain/Activity/Models/TaskActivity.php
- app/Domain/Activity/Services/TaskActivityRecorder.php
- app/Models/User.php
- app/Domain/Tasks/Actions/ChangeTaskStatus.php
- app/Domain/Tasks/Actions/ReorderTasks.php
- app/Domain/Tasks/Queries/MyWorkQuery.php
- app/Domain/Tasks/Queries/TaskKeyQuery.php
- app/Domain/Analytics/Queries/AnalyticsQueryService.php
- app/Domain/Export/Queries/ExportQueryService.php
- app/Policies/ProjectPolicy.php
- app/Policies/TaskPolicy.php
- app/Policies/LabelPolicy.php
- app/Http/Requests/ReorderTasksRequest.php
- app/Http/Controllers/ExportController.php
- app/Http/Controllers/ProfileController.php
- routes/web.php
- database/factories/
- database/seeders/
- resources/views/
- composer.json
- .env.example
- package.json
- docs/architecture/domain-contracts.md
- docs/architecture/stack.md
- docs/ui/screen-spec.md
- planops-complete-spec.md

---

## Dependency order

DYX-000 -> DYX-001 -> DYX-002 -> DYX-003 -> DYX-004 -> DYX-005 -> DYX-006 -> DYX-007

DYX-005 may begin after DYX-002 and DYX-001. DYX-006 may begin after DYX-003 and DYX-004. DYX-007 requires all prior tasks.

---

## Task DYX-000: Baseline and contract freeze

**Files:**

- Modify: docs/PlanOps_Sprint_2.md
- Modify: docs/architecture/domain-contracts.md
- Modify: docs/architecture/stack.md
- Modify: docs/ui/screen-spec.md
- Modify: planops-complete-spec.md
- Review: docs/superpowers/plans/2026-08-20-planops-implementation.md

**Interfaces:**

- Consumes: current repository behavior and the accepted decisions in docs/PlanOps_Sprint_2.md.
- Produces: one baseline report and synchronized collaboration vocabulary.

- [ ] **Step 1: Capture the baseline**

Run:

~~~text
php artisan test
npm.cmd run build
php artisan route:list --except-vendor
rg -n "ownedBy|user_id|TaskActivityRecorder|Playwright|axe|queue|notifications" app routes database resources docs composer.json package.json
~~~

Record failures by command, suite, and first actionable cause. Do not call a failing baseline a collaboration regression.

- [ ] **Step 2: Update the authority documents**

Replace claims that projects/tasks/labels are permanently user-owned. Add membership, owner_id, actor_user_id, project_events, project-scoped labels, the route table, the queue decision, and the PostgreSQL/browser release gates. Mark historical text superseded where it cannot be rewritten safely.

- [ ] **Step 3: Verify the freeze**

Run:

~~~text
git diff --check
rg -n "projects\\.user_id|tasks\\.user_id|ownedBy\\(|no roles|no teams|no queues|no browser" docs planops-complete-spec.md
~~~

Expected: every remaining match is labeled as legacy input, migration compatibility, or an explicit out-of-scope item.

- [ ] **Step 4: Commit**

~~~text
git add docs/PlanOps_Sprint_2.md docs/architecture docs/ui planops-complete-spec.md
git commit -m "docs: freeze collaboration contract and baseline"
~~~

---

## Task DYX-001: Schema, invariants, and migration

**Files:**

- Create: new additive migrations in database/migrations/
- Create: app/Domain/Collaboration/Enums/ProjectRole.php
- Create: app/Domain/Collaboration/Models/ProjectMembership.php
- Create: app/Domain/Collaboration/Models/ProjectInvitation.php
- Create: app/Domain/Collaboration/Models/ProjectEvent.php
- Create: app/Domain/Collaboration/Enums/ProjectEventType.php with
  INVITATION_CREATED, INVITATION_ACCEPTED, INVITATION_REVOKED,
  INVITATION_RESENT, MEMBER_REMOVED, MEMBER_ROLE_CHANGED, and
  OWNERSHIP_TRANSFERRED.
- Modify: app/Domain/Projects/Models/Project.php
- Modify: app/Domain/Tasks/Models/Task.php
- Modify: app/Domain/Labels/Models/Label.php
- Modify: app/Domain/Activity/Models/TaskActivity.php
- Modify: app/Models/User.php
- Modify: database/factories/ and database/seeders/
- Test: tests/Feature/Collaboration/DatabaseInvariantTest.php
- Test: tests/Feature/Collaboration/MigrationBackfillTest.php

**Interfaces:**

- Produces: ProjectMembership::active(), ProjectInvitation::normalizedEmail(), Project::owner(), Project::memberships(), Project::activeMemberships(), Project::events(), Task::assignee(), Task::creator(), Task::accessibleBy(), and immutable ProjectEvent creation.
- Preserves: the current legacy columns until the access cutover is verified.

- [ ] **Step 1: Write failing invariant tests**

Test exactly one active Owner, owner_id/membership agreement, membership reactivation, canonical invitation uniqueness, nullable assignment, and project-scoped label uniqueness. Include a migration fixture with two projects owned by different users and a legacy label attached to tasks in both projects.

- [ ] **Step 2: Run the focused tests**

Run:

~~~text
php artisan test tests/Feature/Collaboration/DatabaseInvariantTest.php tests/Feature/Collaboration/MigrationBackfillTest.php
~~~

Expected: FAIL because the collaboration tables/columns and models do not exist.

- [ ] **Step 3: Add additive schema and constraints**

Add owner_id, project_memberships, project_invitations, project_events,
created_by_user_id, assignee_id, actor_user_id, normalized_email, removed_at,
removed_by_user_id, last_sent_at, users.deactivated_at, and labels.project_id.
Add indexes for active membership, viewer/project lookup, assignment/status
lookup, invitation expiry, and event chronology.

Create PostgreSQL constraints equivalent to:

~~~sql
CREATE UNIQUE INDEX project_memberships_one_active_owner
ON project_memberships (project_id)
WHERE role = 'OWNER' AND removed_at IS NULL;

CREATE UNIQUE INDEX project_invitations_pending_email
ON project_invitations (project_id, normalized_email)
WHERE accepted_at IS NULL AND revoked_at IS NULL;

CREATE UNIQUE INDEX projects_global_key
ON projects (LOWER(key));
~~~

Use nullOnDelete or retained/anonymized user rows for historical references. Keep UNIQUE(project_id, user_id) so reactivation updates the existing membership.

- [ ] **Step 4: Implement the backfill**

Backfill owner_id from projects.user_id, create/upsert the Owner membership, backfill task creators and assignees, backfill activity actors from legacy user_id, infer project-scoped labels, and report duplicate project keys before enforcing the global index. Preserve project/task/activity counts.

- [ ] **Step 5: Run migration verification**

Run the migrations on a fresh PostgreSQL database and on a copy containing
legacy data. Run the idempotent backfill command a second time and assert that
it creates no duplicate rows. Assert no orphan rows, exactly one active Owner
per project, valid assignees, stable task display keys, and no duplicate
project/normalized-label key.

- [ ] **Step 6: Commit**

~~~text
git add app/Domain/Collaboration app/Domain/Projects/Models/Project.php app/Domain/Tasks/Models/Task.php app/Domain/Labels/Models/Label.php app/Domain/Activity/Models/TaskActivity.php database/migrations database/factories database/seeders tests/Feature/Collaboration
git commit -m "feat: add collaboration schema and invariant backfill"
~~~

---

## Task DYX-002: Access scopes and policy migration

**Files:**

- Modify: app/Domain/Projects/Models/Project.php
- Modify: app/Domain/Tasks/Models/Task.php
- Modify: app/Policies/ProjectPolicy.php
- Modify: app/Policies/TaskPolicy.php
- Modify: app/Policies/LabelPolicy.php
- Modify: routes/web.php
- Modify: app/Http/Requests/ReorderTasksRequest.php
- Modify: app/Domain/Tasks/Actions/ReorderTasks.php
- Modify: app/Domain/Tasks/Queries/
- Modify: app/Domain/Analytics/Queries/AnalyticsQueryService.php
- Modify: app/Domain/Export/Queries/ExportQueryService.php
- Modify: app/Http/Controllers/ExportController.php
- Test: tests/Feature/Collaboration/AccessScopeTest.php
- Test: tests/Feature/Collaboration/PolicyMatrixTest.php

**Interfaces:**

- Produces: Project::query()->accessibleBy(User|int $viewer), Task::query()->accessibleBy(User|int $viewer), and the policy abilities listed in the spec.
- Preserves: 404 semantics for inaccessible resources and existing route names where possible.

- [ ] **Step 1: Write failing access tests**

Cover Owner/Admin/Member/non-member access to project/task reads, archived read-only behavior, nested project/task mismatch, removed membership, Member status versus content updates, label access, and project-level export/viewAnalytics.

- [ ] **Step 2: Run the focused tests**

Run:

~~~text
php artisan test tests/Feature/Collaboration/AccessScopeTest.php tests/Feature/Collaboration/PolicyMatrixTest.php
~~~

Expected: FAIL because existing models and policies use user_id/ownedBy().

- [ ] **Step 3: Implement canonical scopes**

Use active membership joins for projects and tasks. Replace route bindings in routes/web.php with accessibleBy(viewer), add scopeBindings() or explicit project_id checks, and rename local owner variables to viewer. Do not leave a security-critical ownedBy() query in a controller, action, selector, export, search, dashboard, analytics, or activity path.

- [ ] **Step 4: Close policy gaps**

Add viewAny, view, create, update, changeStatus, archive, restore, invite, manageMembers, manageRoles, transferOwnership, export, and viewAnalytics to ProjectPolicy. Add view, create, update, changeStatus, changePriority, changeDueDate, assign, delete, restore, and reorder to TaskPolicy. Add project-aware label view/create/delete/attach/detach checks.

Make ReorderTasksRequest and ReorderTasks authorize reorder. Make ExportController projects/tasks accept ExportRequest and authorize each export scope before streaming.

- [ ] **Step 5: Verify every query surface**

Run the access tests plus the existing project, task, search, activity, dashboard, analytics, My Work, label, and export tests. Add assertions that no inaccessible rows appear before pagination or serialization.

- [ ] **Step 6: Commit**

~~~text
git add app/Domain/Projects app/Domain/Tasks app/Domain/Analytics app/Domain/Export app/Policies app/Http/Requests app/Http/Controllers/ExportController.php routes tests/Feature/Collaboration
git commit -m "feat: enforce membership-aware access and policies"
~~~

---

## Task DYX-003: Member and invitation lifecycle

**Files:**

- Create: app/Domain/Collaboration/Actions/
- Create: app/Http/Controllers/Collaboration/
- Create: app/Http/Controllers/InvitationAcceptanceController.php
- Create: app/Http/Requests/Collaboration/
- Modify: routes/web.php
- Modify: app/Http/Controllers/ProfileController.php
- Modify: resources/views/
- Test: tests/Feature/Collaboration/InvitationLifecycleTest.php
- Test: tests/Feature/Collaboration/MemberManagementTest.php
- Test: tests/Feature/Collaboration/InvitationConcurrencyTest.php

**Interfaces:**

- InviteProjectMember::handle(User $actor, Project $project, string $email, ProjectRole $role): ProjectInvitation
- AcceptProjectInvitation::handle(User $user, string $rawToken): ProjectMembership
- RevokeProjectInvitation::handle(User $actor, ProjectInvitation $invitation): void
- ResendProjectInvitation::handle(User $actor, ProjectInvitation $invitation): ProjectInvitation
- RemoveProjectMember::handle(User $actor, Project $project, User $subject): void
- ChangeProjectMemberRole::handle(User $actor, ProjectMembership $membership, ProjectRole $role): ProjectMembership
- TransferProjectOwnership::handle(User $actor, Project $project, User $newOwner): Project

- [ ] **Step 1: Write failing lifecycle tests**

Cover canonical email, Owner/Admin invite restrictions, one pending invitation, token preview privacy, email mismatch, expiry, revoke, resend rotation, accept exactly once, membership reactivation, removal/unassignment, role changes, Owner protection, and atomic ownership transfer.

- [ ] **Step 2: Run focused tests**

Run:

~~~text
php artisan test tests/Feature/Collaboration/InvitationLifecycleTest.php tests/Feature/Collaboration/MemberManagementTest.php tests/Feature/Collaboration/InvitationConcurrencyTest.php
~~~

Expected: FAIL because routes/actions do not exist.

- [ ] **Step 3: Implement domain actions**

Hash a secure random token with SHA-256, store only token_hash, use seven UTC days from configuration, lock project/invitation/membership rows, handle unique-index conflicts by reloading the pending row, and append project_events. Do not log the raw token.

- [ ] **Step 4: Add routes and request validation**

Implement public GET token preview with generic state only; authenticated POST accept with CSRF, verified/canonical email match, rate limits, and no project data before authentication. Add Team, invite, revoke, resend, member removal, role, and transfer routes with policy checks.

- [ ] **Step 5: Guard account deletion**

Update ProfileController so an Owner cannot delete an account while owning projects. Retain or anonymize non-owner identities without cascading historical task/activity/project event rows.

- [ ] **Step 6: Run tests and commit**

Run the focused suite and then:

~~~text
git add app/Domain/Collaboration app/Http/Controllers app/Http/Requests routes/web.php app/Http/Controllers/ProfileController.php resources/views tests/Feature/Collaboration
git commit -m "feat: add project membership and invitation lifecycle"
~~~

---

## Task DYX-004: Assignment, actor history, and My Work

**Files:**

- Create: app/Domain/Tasks/Actions/AssignTask.php
- Modify: app/Domain/Tasks/Actions/ChangeTaskStatus.php
- Modify: app/Domain/Activity/Services/TaskActivityRecorder.php
- Modify: app/Domain/Activity/Models/TaskActivity.php
- Modify: app/Domain/Activity/Enums/TaskActivityType.php
- Modify: all existing task and label mutation actions
- Modify: app/Domain/Tasks/Queries/MyWorkQuery.php
- Modify: task controllers and resources/views/
- Test: tests/Feature/Collaboration/AssignmentTest.php
- Test: tests/Feature/Collaboration/MemberStatusTest.php
- Test: tests/Feature/Collaboration/ActivityActorTest.php
- Test: tests/Feature/MyWork/MyWorkCollaborationTest.php

**Interfaces:**

- AssignTask::handle(User $actor, Task $task, ?User $assignee): Task
- TaskActivityRecorder::record(User $actor, Task $task, TaskActivityType $type, ?string $field, mixed $oldValue, mixed $newValue, array $metadata = []): TaskActivity
- ChangeTaskStatus::handle(User $user, Task $task, TaskStatus|string $status): Task
- MyWorkQuery::paginate(User $viewer, array $filters = [], int $perPage = 50): LengthAwarePaginator

- [ ] **Step 1: Write failing tests**

Cover assign/reassign/unassign, cross-project/non-member assignees, no-op suppression, removal versus assignment race, actor IDs, Member status on own task, forbidden status on another/unassigned task, forbidden CANCELLED, and My Work across multiple projects.

- [ ] **Step 2: Run focused tests**

Run:

~~~text
php artisan test tests/Feature/Collaboration/AssignmentTest.php tests/Feature/Collaboration/MemberStatusTest.php tests/Feature/Collaboration/ActivityActorTest.php tests/Feature/MyWork/MyWorkCollaborationTest.php
~~~

Expected: FAIL because assignment and actor-aware policy paths are absent.

- [ ] **Step 3: Implement assignment and status transaction rules**

Authorize task.assign before the transaction; lock project, membership rows, and task in that order; revalidate the target membership and project state; write assignee and ASSIGNEE_CHANGED activity only when the value changes; dispatch the event after commit. Change ChangeTaskStatus to authorize changeStatus, use accessibleBy(), revalidate assignment, and reject CANCELLED for Members.

- [ ] **Step 4: Update the recorder and every mutation caller**

Require the actor parameter. Backfill legacy user_id to actor_user_id, update all listed task/label actions, and render actor names from actor_user_id or safe history metadata.

- [ ] **Step 5: Rewrite My Work**

Filter assignee_id to the viewer and require active membership through the task project. Preserve pagination, sorting, filters, and migrated personal-task visibility. Remove owner-only label filters.

- [ ] **Step 6: Run tests and commit**

~~~text
git add app/Domain/Tasks app/Domain/Activity app/Domain/Labels app/Http resources/views tests/Feature/Collaboration tests/Feature/MyWork
git commit -m "feat: add assignment actor history and collaboration My Work"
~~~

---

## Task DYX-005: Project-scoped labels

**Files:**

- Modify: database/migrations/
- Modify: app/Domain/Labels/Models/Label.php
- Modify: app/Domain/Labels/Actions/
- Modify: app/Policies/LabelPolicy.php
- Modify: task-label queries and resources/views/components/labels/
- Test: tests/Feature/Collaboration/ProjectScopedLabelTest.php

**Interfaces:**

- Label::scopeAccessibleBy(Builder $query, User|int $viewer): Builder
- Label::scopeForProject(Builder $query, Project|int $project): Builder
- CreateLabel::handle(User $actor, Project $project, string $name, ?string $color = null): Label

- [ ] **Step 1: Write failing label tests**

Cover one label taxonomy per project, cross-project legacy duplication, normalized-name uniqueness, unattached labels, Member read access, and Owner/Admin mutation authorization.

- [ ] **Step 2: Run the focused test**

Run:

~~~text
php artisan test tests/Feature/Collaboration/ProjectScopedLabelTest.php
~~~

Expected: FAIL because Label has user_id ownership and no project scope.

- [ ] **Step 3: Implement the label backfill and scopes**

Infer project IDs from task_label attachments, duplicate a legacy label when it spans projects, deduplicate within each project, preserve merged names in activity metadata, and leave unattached labels out of project selectors until assigned.

- [ ] **Step 4: Verify labels in all selectors**

Run label management, task list, board, search, My Work, and cross-project access tests. Assert a Member can view the project label set but cannot create, delete, attach, or detach labels.

- [ ] **Step 5: Commit**

~~~text
git add database/migrations app/Domain/Labels resources/views tests/Feature/Collaboration/ProjectScopedLabelTest.php
git commit -m "feat: make labels project-scoped"
~~~

---

## Task DYX-006: After-commit notifications

**Files:**

- Create: notification migrations/models/classes/listeners
- Modify: app/Domain/Collaboration/Actions/InviteProjectMember.php
- Modify: app/Domain/Tasks/Actions/AssignTask.php
- Modify: composer.json
- Modify: .env.example
- Modify: routes/web.php
- Modify: resources/views/
- Test: tests/Feature/Notifications/

**Interfaces:**

- ProjectInvitationCreated and TaskAssigned events carry project/task IDs, recipient ID, event ID, and safe display snapshots.
- Database notifications expose unread/read state and target reauthorization.
- Mail delivery uses the existing Laravel queue configuration with bounded retries and failure logging.

- [ ] **Step 1: Write failing notification tests**

Cover after-commit dispatch, database notification payload, email target, no-op assignment suppression, duplicate retry suppression, mark read, mark all read, unread count, removed-member suppression, and inaccessible target links.

- [ ] **Step 2: Run the focused tests**

Run:

~~~text
php artisan test tests/Feature/Notifications
~~~

Expected: FAIL because notification persistence/routes/listeners do not exist.

- [ ] **Step 3: Implement idempotent after-commit delivery**

Dispatch only after the invitation/assignment transaction commits. Use event ID plus recipient ID as the idempotency key, store only safe payload snapshots, authorize target links at read time, and log failures with a correlation ID. Do not roll back the business transaction when delivery fails.

- [ ] **Step 4: Align queue configuration**

Make composer.json, .env.example, and docs/architecture/stack.md agree on queue:listen/worker behavior, retry limits, and whether email is enabled. Do not require a permanent worker for P0 acceptance.

- [ ] **Step 5: Run tests and commit**

~~~text
git add app/Notifications app/Domain/Collaboration app/Domain/Tasks composer.json .env.example routes resources/views tests/Feature/Notifications
git commit -m "feat: add idempotent after-commit notifications"
~~~

---

## Task DYX-007: Release verification and documentation reconciliation

**Files:**

- Modify: tests/Feature/
- Create: tests/Browser/Collaboration/
- Create: playwright.config.ts
- Create: tests/Browser/axe.setup.ts
- Modify: package.json
- Modify: docs/architecture/
- Modify: docs/ui/
- Modify: docs/PlanOps_Sprint_2.md

**Interfaces:**

- Produces: a release report showing database, authorization, concurrency, UI, accessibility, build, and documentation gates.

- [ ] **Step 1: Add browser and accessibility tooling**

Add Playwright and axe-core to package.json, configure the application URL and test database, and cover invite/accept, Team, assignment, Member status, forbidden edit, notification read state, keyboard-only navigation, focus return, validation, and 404/403 states.

- [ ] **Step 2: Run the full verification suite**

Run:

~~~text
php artisan test
npm.cmd run build
npx playwright test
rg -n "ownedBy\\(|projects\\.user_id|tasks\\.user_id|labels\\.user_id|no roles|no teams|no queues|no browser" app routes database resources docs composer.json package.json
~~~

Expected: zero unapproved test failures, zero skipped security/concurrency tests, successful build/browser tests, and only explicitly labeled legacy or deferred matches.

- [ ] **Step 3: Verify migration and concurrency**

Run the PostgreSQL migration rehearsal and concurrency tests for duplicate invitation, concurrent acceptance, assignment/removal, role changes, ownership transfer, and task numbering. Compare the preservation report with the preflight counts.

- [ ] **Step 4: Reconcile contracts**

Update docs/architecture/domain-contracts.md, docs/architecture/stack.md, docs/ui/screen-spec.md, and planops-complete-spec.md so routes, models, actor fields, labels, queue, browser tests, and release gates match the implementation.

- [ ] **Step 5: Commit the release gate**

~~~text
git add tests package.json docs
git commit -m "test: close collaboration release gates and reconcile docs"
~~~

- [ ] **Step 6: Handoff**

Publish the release report with the exact commands, results, known baseline exceptions, migration report, and unresolved blockers. A passing collaboration subset without a full-suite result is not a release approval.
