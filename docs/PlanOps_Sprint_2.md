# PlanOps Sprint 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. The sprint tasks below use checkbox syntax for tracking.

**Goal:** Move PlanOps from user-owned projects to a secure, project-scoped collaboration model with invitations, roles, one assignee per task, actor-aware history, and assignment-based My Work.

**Architecture:** Preserve the existing Laravel domain boundaries, but replace owner-only access with active project membership and explicit policies. Use additive PostgreSQL migrations, transactional membership/assignment changes, after-commit events for notifications, and a narrow P0 vertical slice before collaboration analytics or notification polish.

**Tech Stack:** PHP 8.3+, Laravel 13, Livewire 4, Blade, Tailwind, PostgreSQL-compatible persistence, Pest 4, Vite.

**Spec:** `docs/PlanOps_Sprint_2.md`

---

## PlanOps — Next Sprint Specification
### Collaborative Projects, Groups/Teams, Roles, Invitations, Task Assignment & Notifications

**Project:** PlanOps  
**Target stack:** PHP 8.3+, Laravel 13, Blade/Tailwind, PostgreSQL-compatible persistence, Pest 4  
**Sprint type:** Architectural feature sprint  
**Primary release target:** `PlanOps 1.1 — Collaboration Foundation`  
**P0 release boundary:** secure collaboration foundation and one tested end-to-end assignment flow.
**P1 target:** database/email notifications, notification center, assignee filters, and collaboration-aware dashboard counts after P0 is green.

---

# 1. Sprint Executive Summary

PlanOps currently has a strong single-user project/task-management core: projects, tasks, subtasks, statuses, priorities, due dates, boards, My Work, activity history, search, analytics, exports, labels, preferences, policies, and a substantial Pest test suite.

The next important evolution is to make PlanOps **collaborative**.

Today, the application is fundamentally based on this ownership model:

```text
User
 └── Project
      └── Task
```

The next sprint changes the model to:

```text
User
  │
  ├──────── ProjectMembership ─────── Project
  │               │                     │
  │               └── role              ├── Tasks
  │                                     │    └── assignee
  │                                     ├── Labels
  │                                     ├── Activity
  │                                     └── Analytics
  │
  └──────── Notifications / My Work
```

The goal is not to create a complicated Jira-style organization system. The goal is to add a **simple, coherent collaboration layer** around the existing PlanOps project domain.

At the end of the sprint, a project should be able to contain several people, each with a project-scoped role. Owners/Admins should be able to invite people and assign tasks. Members should be able to see the project but only operate on the work assigned to them according to a restricted permission model.

This sprint creates the foundation for the later PlanOps vision:

```text
Create Project
      ↓
Invite Team
      ↓
Define Tasks
      ↓
Assign Work
      ↓
My Work
      ↓
Daily Planning
      ↓
Execution
      ↓
Activity + Notifications
      ↓
Analytics / Project Health
```

---

# 1.1 Document Authority and Contract Freeze

This document is the implementation contract for Sprint 2. It supersedes conflicting collaboration guidance in the following baseline documents:

- `planops-complete-spec.md`;
- `docs/architecture/domain-contracts.md`;
- `docs/architecture/stack.md`;
- `docs/ui/screen-spec.md`;
- `docs/superpowers/plans/2026-08-20-planops-implementation.md`.

Those files may remain useful historical references, but they must be synchronized or explicitly marked superseded before code work is merged. No implementation task is complete while the code and these contracts disagree.

The existing app is still a single-owner application. The current route bindings, policies, queries, label ownership, activity actor field, export authorization, profile deletion, and test baseline are therefore migration inputs, not proof that the collaboration design is already implemented.

Baseline snapshot before collaboration work (2026-09-05): two fresh
`php artisan test` runs reported 64 failures, 186 passes, and 1 skipped (1,028
assertions); `npm.cmd run build` passed. The earlier one-test variance was
caused by random task numbers and implicit sort fixture dates, which are now
explicit. Full evidence is recorded in
[`docs/baselines/2026-09-05-sprint-2-baseline.md`](baselines/2026-09-05-sprint-2-baseline.md).
These are pre-existing repository conditions and must be resolved or
explicitly quarantined before the collaboration release gate can be green.

## Release gates before feature work

1. Capture the current test/build baseline and fix or quarantine unrelated baseline failures with named issues.
2. Produce a migration preflight report for duplicate project keys, orphaned references, missing owners, and activity rows.
3. Add the collaboration invariants and access-scope tests before changing controller behavior.
4. Update the architecture and UI contracts in the same change set as the implementation, or record an explicit exception in the release checklist.

The canonical vocabulary is:

~~~text
viewer = the authenticated user making the request
active member = project membership where removed_at IS NULL
owner = the one active membership with role OWNER
creator = tasks.created_by_user_id
assignee = nullable tasks.assignee_id
actor = the authenticated user who performed the action
~~~

# 1.2 Revised Sprint Boundary

The P0 slice is intentionally narrow: secure access migration, member/invitation lifecycle, minimal ownership transfer, one assignee per task, actor-aware task activity, assignment-based My Work, and complete read/export scoping.

P1 begins only after P0 passes the database, concurrency, authorization, and
migration gates. Browser, keyboard, and accessibility checks remain mandatory
before final release. P1 contains notification center/email delivery, assignee
filters, and collaboration-aware dashboard counts. WebSockets,
productivity scoring, and organization/workspace abstractions remain out of
scope.

---

# 2. Product Direction

## 2.1 Updated positioning

PlanOps should evolve from:

> A personal project and task manager.

into:

> **A collaborative work-planning and execution platform that helps teams organize projects, assign responsibilities, track progress, and help each member know what they should work on next.**

PlanOps should support two complementary perspectives.

### Manager / Admin perspective

The manager should be able to answer:

- What needs to be done?
- Who owns each task?
- What work is unassigned?
- What is blocked?
- What is overdue?
- What has changed recently?
- How is the project progressing?

### Member perspective

The regular member should be able to answer:

- What is assigned to me?
- What should I work on now?
- What is due soon?
- What is blocked?
- What requires my attention?
- What status can I update?

This separation is important because PlanOps should not become only a manager dashboard. The application should remain useful to the people actually executing the work.

---

# 3. Terminology Decision: “Group” vs “Project Team”

The requested “group” functionality will be implemented initially as a **Project Team / Project Members** capability rather than a completely separate Group entity.

For this sprint:

```text
Project
 └── Members
      ├── Owner
      ├── Admin
      └── Member
```

In the UI, wording may use:

- **Team**
- **Members**
- **Project members**

A standalone organization/workspace/group hierarchy is deliberately deferred.

### Why

A separate hierarchy such as:

```text
Organization
  └── Group
      └── Project
```

would immediately introduce additional concerns:

- organization ownership;
- organization billing;
- cross-project roles;
- group invitations;
- multiple teams;
- workspace-level visibility;
- inherited permissions;
- organization settings;
- cross-team analytics.

None of these are necessary to deliver collaboration inside PlanOps today.

### Future extension

The proposed `ProjectMembership` model leaves room for a future:

```text
Workspace
 ├── Members
 ├── Teams
 └── Projects
```

without requiring that architecture now.

---

# 4. Sprint Goals

## Primary goals

1. Replace single-owner access assumptions with project membership.
2. Add project-scoped roles.
3. Add project invitations.
4. Add project member management.
5. Add task assignment.
6. Restrict regular members to execution-oriented actions.
7. Make My Work assignment-based.
8. Record the actor behind activity events.
9. Protect all project/task reads by project membership.
10. Record notification events for invitations and assignments; deliver the
    database/email notification experience in P1 after commit.

## Quality goals

1. Preserve existing project/task behavior for single-user users.
2. Preserve existing historical task/activity data.
3. Avoid security regressions caused by route-model binding or direct URLs.
4. Avoid global roles on the `users` table.
5. Keep role logic centralized in policies/domain logic.
6. Keep one human assignee per task.
7. Maintain test coverage for every permission boundary.
8. Make PostgreSQL the authority for constraints and concurrency verification.
9. Keep documentation, routes, queue behavior, and browser-test promises in
   sync with the repository.

---

# 5. Explicit Non-Goals for This Sprint

The following features are **not part of this sprint**:

- organizations/workspaces;
- standalone multi-project groups;
- custom RBAC/permission builder;
- Viewer/Guest role;
- multiple assignees per task;
- comments/chat;
- mentions;
- WebSocket realtime collaboration;
- Google Calendar/Outlook;
- Daily Planning / Today view;
- recurrence;
- milestones;
- dependencies;
- Gantt charts;
- time tracking;
- AI planning;
- team performance scoring;
- public API;
- SSO/SCIM;
- external client/guest portals.

These remain valid future ideas but must not dilute the collaboration foundation.

The following are deliberately split from the P0 foundation rather than silently
left ambiguous:

- P0: active membership, invitation acceptance, Owner protection and minimal ownership transfer, role enforcement, assignment, actor-aware task history, project-scoped labels, My Work, and membership-aware reads.
- P1: database notifications, invitation/assignment email delivery, notification center, assignee filters, and collaboration-aware dashboard counts.
- P2: team workload views, team analytics, realtime delivery, saved views, productivity scoring, role-change feed polish, and broader planning features.

---

# 6. Current Codebase Impact

The uploaded project is already using:

```json
"php": "^8.3",
"laravel/framework": "^13.0",
"livewire/livewire": "^4.4",
"pestphp/pest": "^4.7"
```

The current collaboration refactor directly affects the following existing areas.

## Existing models

- `app/Domain/Projects/Models/Project.php`
- `app/Domain/Tasks/Models/Task.php`
- `app/Domain/Labels/Models/Label.php`
- `app/Domain/Activity/Models/TaskActivity.php`
- `app/Models/User.php`

## Existing policies

- `app/Policies/ProjectPolicy.php`
- `app/Policies/TaskPolicy.php`
- `app/Policies/LabelPolicy.php`

## Existing actions that currently depend on ownership

- `CreateProject`
- `UpdateProject`
- `ArchiveProject`
- `RestoreProject`
- `ChangeProjectStatus`
- `CreateTask`
- `UpdateTask`
- `UpdateTaskDetails`
- `ChangeTaskStatus`
- `ChangeTaskPriority`
- `ChangeTaskDueDate`
- `DeleteTask`
- `RestoreTask`
- `ReorderTasks`

## Existing queries that need access semantics changed

- `ProjectIndexQuery`
- `ProjectOverviewQuery`
- `ProjectBoardQuery`
- `ProjectTaskListQuery`
- `TaskDetailQuery`
- `MyWorkQuery`
- `SearchQueryService`
- `DashboardQueryService`
- `AnalyticsQueryService`
- `TaskActivityFeedQuery`
- export queries/controllers

## Critical current ownership assumptions

### Route bindings

The current application binds projects/tasks using:

```php
Project::query()->ownedBy(request()->user())
Task::query()->ownedBy(request()->user())
```

These must become membership-aware.

### `ChangeTaskStatus`

The current action authorizes general `update` and then locks a task using `ownedBy($user)`.

That will become invalid because a regular Member must be able to:

```text
changeStatus = yes
update task details = no
```

Therefore status authorization must become a dedicated policy ability.

### `MyWorkQuery`

The current query uses:

```php
Task::query()->ownedBy($owner)
```

After this sprint, My Work should be based primarily on:

```text
assignee_id = current user
```

across all projects the user can access.

### Activity recorder

The current recorder derives activity `user_id` from the task owner.

Collaboration requires an explicit **actor**.

---

# 7. Collaboration Domain Model

## 7.1 ProjectRole enum

Create:

```text
app/Domain/Collaboration/Enums/ProjectRole.php
```

Values:

```text
OWNER
ADMIN
MEMBER
```

### Important rule

Roles are **project-scoped**, not global.

Never add:

```text
users.role
```

A single user can therefore be:

```text
Project A → OWNER
Project B → ADMIN
Project C → MEMBER
```

This is intentional.

---

# 8. Permission Model

## 8.1 OWNER

The Owner is the project authority.

Owner can:

- view project;
- edit project;
- change project status;
- archive/restore project;
- permanently destructive project operations when introduced;
- create tasks;
- update tasks;
- change task status;
- change priority;
- change due dates;
- delete/restore tasks;
- reorder tasks;
- assign/reassign/unassign tasks;
- manage labels;
- invite people;
- cancel/resend invitations;
- remove members;
- promote/demote Admins/Members;
- transfer ownership;
- export project data;
- view project/team analytics.

There must be exactly one active Owner per project.

---

## 8.2 ADMIN

Admin is an operational project manager/team lead.

Admin can:

- view project;
- edit project content/settings that are not ownership/security critical;
- change project status;
- archive/restore project;
- create tasks;
- edit tasks;
- change priority;
- change due dates;
- delete/restore tasks;
- reorder tasks;
- change any task status;
- assign/reassign/unassign tasks;
- manage project labels;
- invite Members;
- remove regular Members;
- cancel/resend invitations;
- export project data;
- view project/team analytics.

Admin cannot:

- delete the project permanently;
- transfer ownership;
- demote/remove the Owner;
- promote another user to Owner;
- promote/demote Admin roles;
- invite another Admin.

### Required security boundary

Only the Owner manages role elevation and demotion:

```text
MEMBER ↔ ADMIN
```

This prevents one Admin from silently expanding administrative authority.
The Owner role cannot be changed through the generic role endpoint; only
TransferProjectOwnership may move it.

---

## 8.3 MEMBER

Member is execution-focused.

Member can:

- view the project;
- view project tasks;
- view task details;
- view project activity;
- view labels;
- view their own assigned tasks in My Work;
- change status of a task assigned to them.

Member cannot:

- create tasks;
- edit task title;
- edit description;
- change priority;
- change due date;
- assign/reassign tasks;
- delete/restore tasks;
- reorder the board globally if that changes other users' work;
- edit project settings;
- invite members;
- remove members;
- manage roles;
- delete/archive the project;
- export full project data.

### Core member invariant

```text
Member can change task status
ONLY IF
Task.assignee_id == Member.id
```

An unassigned task cannot be updated by a regular Member.

A task assigned to another Member cannot be updated by the current Member.

---

# 9. Permission Matrix

| Capability | Owner | Admin | Member |
|---|:---:|:---:|:---:|
| View project | ✅ | ✅ | ✅ |
| View project tasks | ✅ | ✅ | ✅ |
| Create task | ✅ | ✅ | ❌ |
| Edit task title/description | ✅ | ✅ | ❌ |
| Change any task status | ✅ | ✅ | ❌ |
| Change own assigned task status | ✅ | ✅ | ✅ |
| Change priority | ✅ | ✅ | ❌ |
| Change due date | ✅ | ✅ | ❌ |
| Assign task | ✅ | ✅ | ❌ |
| Reassign task | ✅ | ✅ | ❌ |
| Unassign task | ✅ | ✅ | ❌ |
| Delete/restore task | ✅ | ✅ | ❌ |
| Reorder project tasks | ✅ | ✅ | ❌ |
| Manage labels | ✅ | ✅ | ❌ |
| Invite Member | ✅ | ✅ | ❌ |
| Invite Admin | ✅ | ❌ | ❌ |
| Remove Member | ✅ | ✅ | ❌ |
| Change Member/Admin role | ✅ | ❌ | ❌ |
| Edit project | ✅ | ✅ | ❌ |
| Archive/restore project | ✅ | ✅ | ❌ |
| Transfer ownership | ✅ | ❌ | ❌ |
| Delete project permanently | ✅ | ❌ | ❌ |
| Export complete project data | ✅ | ✅ | ❌ |
| Project/team analytics | ✅ | ✅ | ❌ (project progress remains visible in Overview) |

---

# 10. Database Changes

## 10.1 `project_memberships`

Create a dedicated membership table.

```text
project_memberships
────────────────────────────
id
project_id
user_id
role
joined_at
removed_at nullable
removed_by_user_id nullable
created_at
updated_at
```

Constraints:

```text
UNIQUE(project_id, user_id)
```

The row is retained when a member is removed. An active membership is exactly
one row where removed_at IS NULL; re-adding the same user reactivates the
existing row and updates its role/joined timestamp instead of creating a
second row.

PostgreSQL must also enforce one active Owner:

~~~sql
CREATE UNIQUE INDEX project_memberships_one_active_owner
ON project_memberships (project_id)
WHERE role = 'OWNER' AND removed_at IS NULL;
~~~

Application code must additionally lock the project and affected membership
rows when changing roles, removing members, or transferring ownership. The
invariant is:

~~~text
projects.owner_id = the user_id of the one active OWNER membership
~~~

If the database engine is not PostgreSQL-compatible, enforce the same rule
with a serialized transaction and an invariant check before commit.

Indexes:

```text
(project_id)
(user_id)
(project_id, role)
(user_id, project_id)
(project_id, removed_at)
```

Foreign keys:

```text
project_id → projects.id
user_id    → users.id
removed_by_user_id → users.id, nullable
```

Delete behavior:

- deleting a project may cascade membership deletion;
- deleting a user account is blocked while the user owns a project;
- a non-owner account is deactivated/anonymized only through an explicit retention flow;
- task and project event history is never silently cascaded away.

Do not expose a hard-delete profile action until the retention flow preserves
creator, assignee, and actor history. This sprint uses retained identifiers
with a disabled/anonymized profile for historical rows.

Add users.deactivated_at nullable and change the existing profile deletion
action to deactivate the account, revoke sessions, reject new assignments and
invitations, and retain the user row for foreign-key history. Permanent
personal-data erasure is a separate privacy workflow after the history
retention policy has been approved.

## 10.2 Immutable project audit events

Task activity is not enough to explain membership and security changes. Add a
small immutable project event stream for invitation, membership, role, removal,
and ownership changes:

~~~text
project_events
id
project_id
actor_user_id nullable
subject_user_id nullable
event_type
metadata jsonb
created_at
~~~

ProjectEventType values are:

~~~text
INVITATION_CREATED
INVITATION_ACCEPTED
INVITATION_REVOKED
INVITATION_RESENT
MEMBER_REMOVED
MEMBER_ROLE_CHANGED
OWNERSHIP_TRANSFERRED
~~~

Indexes and foreign keys:

~~~text
index(project_id, created_at)
index(actor_user_id, created_at)
project_id → projects.id
actor_user_id → users.id, nullOnDelete
subject_user_id → users.id, nullOnDelete
~~~

Rules:

- events are append-only; there is no update or delete action;
- event_type is a finite application enum;
- metadata stores stable IDs plus safe display snapshots when later account
  anonymization could remove a name;
- actor_user_id is nullable only for future system events;
- project event queries require active project membership;
- membership removal does not erase historical events.

This is the audit trail for security-relevant changes. TaskActivity remains the
task-specific timeline and keeps its own append-only contract.

---

# 11. Project Ownership Migration

The current project model uses:

```text
projects.user_id
```

as ownership.

The target semantic should be explicit:

```text
projects.owner_id
```

## Safe migration strategy

Prefer a staged migration rather than a destructive rename in the first operation.

### Step A

Add:

```text
owner_id nullable
```

### Step B

Backfill:

```text
owner_id = user_id
```

for every existing project.

### Step C

Create an OWNER membership for every existing project owner:

```text
project_memberships
project_id = project.id
user_id    = project.owner_id
role       = OWNER
joined_at  = project.created_at or migration time
```

### Step D

Update application code to use `owner_id` and memberships.

### Step E

Make `owner_id` required after successful backfill.

### Step F

Remove legacy `user_id` from `projects` only after all references/tests have been migrated.

This strategy protects existing data and simplifies rollback/debugging.

## Migration and deployment runbook

Run this sequence against a PostgreSQL staging copy before production:

1. Generate a preflight report for projects without a valid owner, duplicate
   project keys, duplicate task numbers within a project, orphaned tasks,
   orphaned labels, and activities whose user reference cannot be resolved.
2. Add new nullable columns and tables without removing legacy columns.
3. Backfill in bounded batches. Create or upsert the Owner membership before
   enabling membership-aware reads.
4. Backfill task creators, legacy assignees, activity actors, and project
   events. Preserve the pre-migration project/task/activity counts.
5. Resolve duplicate project keys before creating the global key constraint.
   The resolution report must list the old key, new key, project ID, and
   affected task display keys.
6. Validate counts, foreign keys, membership uniqueness, exactly one active
   Owner, owner_id/membership agreement, and active-assignee membership.
7. Deploy dual-read/dual-write compatibility code, then switch reads to the
   new columns and membership scopes.
8. Keep a rollback migration that restores legacy reads while the additive
   columns remain. Remove legacy columns/indexes only in a later release after
   the cutover report is accepted.

The migration must be repeatable on a fresh database, and factories/seeders
must create the same invariants as production migrations. Migrations run once;
the backfill/report command must be safe to rerun without duplicate memberships,
events, labels, or assignments.

## Project key decision

Project keys are globally unique in the collaboration model because task keys
such as PLAN-42 are rendered and searched outside an owner-only scope. The
migration must resolve existing per-user collisions before enforcing the
global case-insensitive unique index. Canonical writes trim and uppercase the
key, while the database index uses LOWER(key). If product later requires duplicate keys, the task
identifier format must change in a separate design; this sprint does not keep
an ambiguous key.

---

# 12. Task Ownership → Creator + Assignee

The current task schema uses:

```text
tasks.user_id
```

which currently means the user who owns the task/project.

That concept becomes ambiguous with collaboration.

The target schema should distinguish:

```text
created_by_user_id
assignee_id nullable
```

Target task model:

```text
tasks
────────────────────────────
id
project_id
created_by_user_id
assignee_id nullable
parent_task_id nullable
number
title
description
status
priority
due_on
position
first_started_at
completed_at
cancelled_at
status_changed_at
deleted_at
created_at
updated_at
```

## Backfill

For existing tasks:

```text
created_by_user_id = existing user_id
assignee_id        = existing user_id OR null
```

### Migration default

For existing personal tasks, set:

```text
assignee_id = existing user_id
```

because the previous single user was effectively responsible for all work.

This means existing My Work behavior remains intuitive immediately after migration.

Database rules:

- created_by_user_id remains required for normal tasks;
- assignee_id is nullable and uses nullOnDelete or an explicit anonymization
  path;
- assignee_id may never be validated by a user-only foreign key check;
- every assignment write locks the task and revalidates active membership;
- add an index on project_id, assignee_id, status for task lists and My Work;
- keep the existing unique index on project_id, number for stable task keys.

If an actor or creator account is anonymized, keep the row ID and a safe
display snapshot so historical task/activity records remain understandable.

---

# 13. Assignee Rules

PlanOps v1 collaboration supports **one human assignee per task**.

Valid states:

```text
Assignee: Sara
```

or:

```text
Assignee: Unassigned
```

Not supported:

```text
Assignee: Sara + Ali + Hamza
```

## Invariant

A task can only be assigned to an active member of its own project.

Formally:

```text
Task.assignee_id ∈ Project.activeMembers.user_id
```

or:

```text
Task.assignee_id = null
```

## Subtasks

Subtasks may be assigned independently.

Example:

```text
PLAN-50 Implement invitation system
  ├── PLAN-51 Invitation model       → Ali
  ├── PLAN-52 Invitation email       → Sara
  └── PLAN-53 Acceptance UI          → Youssef
```

This preserves single ownership while supporting collaborative work decomposition.

---

# 14. `project_invitations`

Create:

```text
project_invitations
────────────────────────────
id
project_id
email
normalized_email
role
invited_by_user_id
token_hash
expires_at
accepted_at
revoked_at
last_sent_at
created_at
updated_at
```

Base indexes:

```text
index(project_id)
index(email)
index(token_hash)
index(expires_at)
```

Prevent more than one active invitation for the same:

```text
project + normalized email
```

Enforce this at both the database and domain levels. Normalize by trimming and
lower-casing the canonical email value before every comparison or write.

Required PostgreSQL constraints:

~~~sql
CREATE UNIQUE INDEX project_invitations_pending_email
ON project_invitations (project_id, normalized_email)
WHERE accepted_at IS NULL AND revoked_at IS NULL;

CREATE UNIQUE INDEX project_invitations_token_hash
ON project_invitations (token_hash);
~~~

An expired invitation remains the one pending row for that project/email until
it is accepted, revoked, or reused by a resend operation. Resend rotates the
token hash and expiry on that row. The token hash is a fixed SHA-256 hex value;
token_hash is required and raw tokens never appear in logs, analytics, stored
metadata, or URLs other than the initial acceptance link.

---

# 15. Invitation Status

Do not persist a status column in P0. Derive status from accepted_at,
revoked_at, and expires_at so one source of truth controls lifecycle rules.

Derived statuses:

```text
ACCEPTED → accepted_at != null
REVOKED  → revoked_at != null
EXPIRED  → now > expires_at
PENDING  → none of the above
```

This avoids conflicting state such as:

```text
status = PENDING
accepted_at != null
```

If a reporting query needs a status value, derive it in one named scope/service;
do not add a second mutable lifecycle state in this sprint.

---

# 16. Invitation Token Security

Never persist the raw invitation token.

Flow:

```text
Generate secure random raw token
          ↓
Send raw token in email URL
          ↓
Store SHA-256/token hash in DB
          ↓
Acceptance request hashes incoming raw token
          ↓
Compare against stored hash
```

Example URL:

```text
/invitations/{rawToken}
```

Database:

```text
token_hash = hash(rawToken)
```

Invitation expiration should be finite.

Initial product rule:

```text
expires in 7 days
```

Make the duration configurable rather than scattering the value in controllers.

---

# 17. Invitation Lifecycle

```text
Owner/Admin enters email
        ↓
Authorize invitation action
        ↓
Normalize email
        ↓
Check existing member
        ↓
Check existing active invitation
        ↓
Create invitation
        ↓
Create pending invitation and dispatch delivery after commit when enabled
        ↓
Invitee opens link
        ↓
Validate token + expiry + revocation
        ↓
Account exists?
   ┌─────────────┐
   │             │
  YES            NO
   │             │
 Login        Register
   │             │
   └──────┬──────┘
          ↓
Ensure authenticated email matches invitation email
          ↓
Accept invitation transaction
          ↓
Create ProjectMembership
          ↓
Set accepted_at
          ↓
User gains project access
```

---

# 18. Invitation Business Rules

1. Do not invite an existing project member.
2. Do not allow duplicate pending invitation for the same project/email.
3. Only Owner/Admin may invite.
4. Only Owner may invite someone directly as Admin.
5. Member invitation role defaults to `MEMBER`.
6. OWNER is never an invitation role; ownership changes only through
   TransferProjectOwnership.
7. Invitation acceptance must match the authenticated user email.
8. Expired invitations cannot be accepted.
9. Revoked invitations cannot be accepted.
10. Accepted invitations cannot be accepted again.
11. Resend should invalidate the previous token or update expiry/token safely.
12. Email comparison should be normalized case-insensitively.

Canonicalize users.email and project_invitations.normalized_email by trimming
and lower-casing at the write boundary. Existing case-only duplicates must be
reported and resolved before adding any case-insensitive unique index.

## 18.1 Invitation security and concurrency contract

The invitation state machine is:

~~~text
PENDING -> ACCEPTED
PENDING -> REVOKED
PENDING -> EXPIRED (derived from expires_at)
EXPIRED -> PENDING (resend rotates token and expiry on the same row)
ACCEPTED and REVOKED are terminal
~~~

The acceptance surface has two stages:

1. Public GET token preview shows only generic invitation state and prompts
   authentication. It must not reveal project data or whether an email belongs
   to an account.
2. Authenticated POST acceptance requires CSRF protection, a canonical email
   match, and a verified email when email verification is available.

Rate-limit token lookup, invitation creation, resend, and acceptance by user,
email, project, and client address. Query by the stored token hash, compare the
full hash, never log the raw token, and prevent token values from becoming
referrer data. A successful acceptance locks the invitation and the target
membership in one transaction, rechecks expiry/revocation/email/member state,
and returns the existing membership when a concurrent request already won.

Concurrent invite requests rely on the partial unique index. On a uniqueness
conflict, reload the existing pending row and return the existing invitation
state rather than creating a second row. Concurrent accept/remove/role changes
use a consistent lock order: project, invitation, membership.

---

# 19. Membership Domain Actions

Create a collaboration domain, for example:

```text
app/Domain/Collaboration/
├── Actions/
├── Enums/
├── Models/
├── Queries/
├── Rules/
└── Services/
```

Required domain actions:

```text
InviteProjectMember
AcceptProjectInvitation
RevokeProjectInvitation
ResendProjectInvitation
RemoveProjectMember
ChangeProjectMemberRole
TransferProjectOwnership
```

TransferProjectOwnership is P0 because the existing profile deletion path
otherwise has no safe exit for a project Owner. The action changes the new
Owner to OWNER and the old Owner to ADMIN in one transaction.

---

# 20. ProjectMembership Model

Create:

```text
app/Domain/Collaboration/Models/ProjectMembership.php
```

Relationships:

```text
ProjectMembership
 ├── project()
 └── user()
```

Casts:

```text
role → ProjectRole enum
joined_at → immutable datetime
```

Helpful methods may include:

```text
isOwner()
isAdmin()
isMember()
canManageWork()
```

However, final authorization decisions should remain in Policies rather than moving all permission logic into model booleans.

---

# 21. Project Relationships

Update `Project` with:

```text
owner()
memberships()
members()
invitations()
```

Required semantics:

```text
owner()       → belongsTo(User::class, 'owner_id')
memberships() → hasMany(ProjectMembership::class)
members()     → belongsToMany(User::class, 'project_memberships') filtered to active rows
```

Keep memberships() unfiltered for audit/history queries. Expose a separate
activeMemberships() relation or named scope for authorization and assignment
eligibility so historical removals cannot accidentally become access grants.

Keep task relationship unchanged:

```text
tasks()
```

---

# 22. User Relationships

Update `User` conceptually with:

```text
ownedProjects()
projectMemberships()
projects()
assignedTasks()
createdTasks()
```

Required meanings:

```text
ownedProjects()      → projects where owner_id = user.id
projectMemberships() → membership rows
projects()           → projects accessible through active membership
assignedTasks()      → tasks.assignee_id = user.id
createdTasks()       → tasks.created_by_user_id = user.id
```

Avoid leaving ambiguous relationship names such as `tasks()` if it is no longer obvious whether that means created or assigned tasks.

---

# 23. Membership-Aware Query Scope

The current project has:

```text
scopeOwnedBy()
```

Collaboration needs a new concept:

```text
scopeAccessibleBy()
```

Conceptually:

```php
Project::accessibleBy($user)
```

returns projects where a membership exists for that user.

For tasks:

```php
Task::accessibleBy($user)
```

returns tasks whose project has a membership for that user.

## Security principle

Never define access as:

```text
user can access task because task.assignee_id == user.id
```

without verifying project membership.

Project membership is the security boundary.

Assignment is a work-responsibility property.

## 23.1 Access-layer migration checklist

Use one canonical scope per resource:

~~~text
Project::accessibleBy(viewer)
Task::accessibleBy(viewer)
~~~

The scope must join only active memberships. Rename owner-oriented local
variables to viewer and remove or deprecate ownedBy() after all callers have
been migrated; do not keep two subtly different security paths.

Audit every query and selector, including ProjectIndexQuery,
ProjectOverviewQuery, ProjectBoardQuery, ProjectTaskListQuery, TaskDetailQuery,
MyWorkQuery, SearchQueryService, DashboardQueryService,
AnalyticsQueryService, TaskActivityFeedQuery, and all export queries.

Audit every controller/action. In the current codebase,
ReorderTasksRequest authorizes only project view while ReorderTasks does not
perform its own policy check; the final action must enforce reorder. The
ExportController must authorize export for each requested scope, and global
exports must include only projects for which the viewer has export permission.
TaskKeyQuery must use project identity/access rather than matching task and
project user_id values.

Every policy entry must be exercised at the request boundary:

~~~text
Project: viewAny, view, create, update, changeStatus, archive, restore,
         invite, manageMembers, manageRoles, transferOwnership, export,
         viewAnalytics
Task:    view, create, update, changeStatus, changePriority, changeDueDate,
         assign, delete, restore, reorder
Label:   view, create, delete, attach, detach
~~~

Nested routes must use scopeBindings() or an explicit project_id check. An
inaccessible resource returns the chosen 404 semantics before rendering or
mutating data. Mutating actions authorize once before the transaction and
revalidate the active membership/role while holding locks inside the
transaction.

---

# 24. Route-Model Binding Refactor

Current route binding uses `ownedBy()`.

Replace with membership-aware binding:

```text
Project::accessibleBy(request()->user())
Task::accessibleBy(request()->user())
```

Additionally verify nested task/project consistency for routes containing both IDs.

Example:

```text
/projects/{project}/board/tasks/{task}/status
```

must verify:

```text
task.project_id == project.id
```

Access to Project A must never allow a crafted URL to mutate a task from Project B.

---

# 25. ProjectPolicy Redesign

Required policy abilities:

```text
viewAny
view
create
update
changeStatus
archive
restore
invite
manageMembers
manageRoles
transferOwnership
export
viewAnalytics
```

Conceptual rules:

```text
view
  → any active project member

update
  → Owner or Admin

changeStatus
  → Owner or Admin

archive / restore
  → Owner or Admin

invite
  → Owner or Admin

manageMembers
  → Owner or Admin with restrictions

manageRoles
  → Owner only

transferOwnership
  → Owner only

export
  → Owner or Admin

viewAnalytics
  → Owner or Admin

view project progress
  → any active project member through the Overview surface only
```

---

# 26. TaskPolicy Redesign

This is one of the most important sprint changes.

Create explicit abilities instead of using generic `update` for everything.

Required abilities:

```text
view
create
update
changeStatus
changePriority
changeDueDate
assign
delete
restore
reorder
```

Rules:

### `view`

```text
user is a project member
```

### `create`

```text
Owner or Admin
```

### `update`

```text
Owner or Admin
```

### `changeStatus`

```text
Owner
OR Admin
OR (Member AND task.assignee_id == user.id)
```

The transition service must reject CANCELLED for Members. Owner/Admin may use
every value in the existing TaskStatus enum, subject to the same transition
validation used by the current application.

### `changePriority`

```text
Owner or Admin
```

### `changeDueDate`

```text
Owner or Admin
```

### `assign`

```text
Owner or Admin
```

### `delete` / `restore`

```text
Owner or Admin
```

### `reorder`

```text
Owner or Admin
```

Archived and cancelled projects are readable by active members but are
read-only. Create, update, assign, reorder, invitation, and membership
mutations require an active project state; restore returns the project to the
normal write state.

---

# 27. Critical Refactor: `ChangeTaskStatus`

Current behavior:

```text
Authorize: update
Query: ownedBy(current user)
```

New behavior:

```text
Authorize: changeStatus
Query: accessible task
Lock task
Verify membership still active inside transaction
Apply transition
Record actor-aware activity
```

This change is mandatory before regular Members can update their assigned task status.

The domain action must not rely only on Blade hiding controls. Authorization must be enforced server-side.

The action must lock the project before the task, use the same lock order as
assignment/removal, and re-read the membership and assignee inside the
transaction. A member whose assignment was removed during the request must
receive a forbidden result and no status/activity row may be written.

---

# 28. Assignment Domain Action

Create:

```text
app/Domain/Tasks/Actions/AssignTask.php
```

or:

```text
ChangeTaskAssignee.php
```

Required flow:

```text
Owner/Admin requests assignment
          ↓
TaskPolicy.assign
          ↓
Validate assignee is null OR active project member
          ↓
Transaction + lock task
          ↓
Revalidate membership inside transaction
          ↓
Change assignee_id
          ↓
Record activity
          ↓
Dispatch assignment event
          ↓
Notify new assignee after commit
```

Supported operations:

```text
Unassigned → Sara
Sara → Youssef
Sara → Unassigned
```

No-op assignment should not create duplicate activity/notification.

---

# 29. Assignment Activity

Add an activity type such as:

```text
ASSIGNEE_CHANGED
```

Examples:

```text
Ali assigned PLAN-42 to Sara.
Ali reassigned PLAN-42 from Sara to Youssef.
Ali unassigned PLAN-42 from Sara.
```

Store IDs in structured values/metadata rather than only formatted text.

Example:

```json
{
  "field": "assignee_id",
  "old_value": {"assignee_id": 4},
  "new_value": {"assignee_id": 7}
}
```

User names should be rendered from referenced user data where appropriate or safely snapshotted in metadata when historical rendering requires it.

---

# 30. Activity Actor Refactor

The current `TaskActivity.user_id` currently behaves like the task owner.

Collaboration requires explicit actor semantics.

Target concept:

```text
actor_user_id
```

Examples:

```text
Sara changed PLAN-42 from NOT_STARTED → IN_PROGRESS.
Ali changed PLAN-42 priority from MEDIUM → HIGH.
Hamza assigned PLAN-42 to Youssef.
```

## Safe migration

1. Add `actor_user_id` nullable.
2. Backfill historical activities from current `user_id`.
3. Update recorder to require/pass actor.
4. Update queries/views.
5. Remove or repurpose legacy `user_id` only after cutover.

The activity record must remain append-only.

---

# 31. Update `TaskActivityRecorder`

Current conceptual API:

```text
record(task, type, field, old, new, metadata)
```

New API should include actor:

```text
record(actor, task, type, field, old, new, metadata)
```

or a clearly typed equivalent.

Never infer actor from:

```text
task.created_by_user_id
assignee_id
project.owner_id
```

The actor is the authenticated user who performed the action.

System-generated activity in the future may support:

```text
actor_user_id = null
actor_type = SYSTEM
```

but that abstraction is not required now.

All existing task actions that mutate state must pass the authenticated actor,
including CreateTask, UpdateTask, UpdateTaskDetails, ChangeTaskStatus,
ChangeTaskPriority, ChangeTaskDueDate, DeleteTask, RestoreTask, ReorderTasks,
and label attach/detach actions.

## 31.1 Project membership audit

Record invitation, acceptance, revocation, resend, member removal, role
change, and ownership transfer in project_events. Each event includes the
actor, affected subject IDs, event type, and structured metadata. Membership
changes are security-relevant and must remain visible to current authorized
project members even after the subject is removed.

---

# 32. My Work Redesign

Current meaning:

> Tasks owned by the current user.

New meaning:

> **Tasks assigned to the current user across all projects they can access.**

Base query:

```text
Task
where assignee_id = current_user.id
and project has active membership for current user
```

Preserve current filters where useful:

- status;
- project;
- priority;
- due;
- label;
- created/updated periods;
- sorting.

Defer these admin-only filters:

```text
Assigned to me
Unassigned
```

These filters apply only to admin project views; global My Work remains
assigned-to-me.

---

# 33. Project Task List / Board Changes

Project task views should support assignee visibility.

Each card/row should show:

```text
PLAN-42     Invitation workflow
HIGH        Sep 12        [SA]
```

where `[SA]` is the assignee avatar/initial.

Unassigned task:

```text
[—]
```

## Owner/Admin interaction

Click assignee control:

```text
Assign task

○ Unassigned
○ Ali Hamdaoui
● Sara Amrani
○ Youssef Karim
```

## Member interaction

Assignee is read-only.

The member can only manipulate the task status when the task is assigned to them.

Members may move an assigned task to any existing non-`CANCELLED` `TaskStatus`
value. Only Owner/Admin may set `CANCELLED` or restore a cancelled task. This
rule is enforced by the transition action and policy, not only by the UI.

---

# 34. Assignee Filters

Add first-class assignee filtering to project task views.

Example:

```text
Assignee
○ Anyone
○ Me
○ Ali
○ Sara
○ Youssef
○ Unassigned
```

Suggested integration targets:

- Project task list;
- Project board;
- Project analytics later;
- Search later if needed.

---

# 35. Project Team / Members UI

Add a project navigation item:

```text
Overview
Board
Tasks
Activity
Analytics
Team
```

or:

```text
Members
```

Required screen:

```text
PROJECT TEAM                                      [Invite member]

AH  Ali Hamdaoui       Owner
SA  Sara Amrani        Member              [•••]
YO  Youssef Karim      Member              [•••]
HB  Hamza Benali       Admin               [•••]

Pending invitations

MA  mariam@example.com Member   Sent 2h ago  [Resend] [Cancel]
```

Role-sensitive controls must be hidden in the UI **and** enforced on the backend.

---

# 36. Invite Member UI

Modal/page:

```text
Invite to PlanOps

Email
┌──────────────────────────────────┐
│ sara@example.com                 │
└──────────────────────────────────┘

Role
● Member
○ Admin   (Owner-only if allowed)

                         [Cancel] [Send invitation]
```

Validation errors should be explicit:

```text
This user is already a project member.
An active invitation already exists for this email.
You cannot invite users as Admin.
```

---

# 37. Member Removal Semantics

When a member is removed from a project:

1. membership is removed/deactivated;
2. project access stops immediately;
3. tasks currently assigned to that member become `Unassigned`;
4. tasks are not deleted;
5. task history is not deleted;
6. activity remains attributable to the historical actor;
7. completed work remains part of analytics/history;
8. future notifications for the project stop.

Example:

```text
Sara removed from PlanOps

PLAN-14 → Unassigned
PLAN-18 → Unassigned
PLAN-22 → Unassigned
```

Perform unassignment and membership removal transactionally where practical.

There is no self-leave shortcut in P0. A Member/Admin who wants to leave must
use an Owner-authorized removal; the Owner must use TransferProjectOwnership
first. Re-adding a removed user reactivates the retained membership row.

---

# 38. Owner Protection

The Owner cannot simply remove themselves or leave the project.

Reject:

```text
Owner → Leave project
```

unless ownership has first been transferred.

Required P0 flow:

```text
Transfer ownership
      ↓
Select existing project member
      ↓
Confirm
      ↓
Old OWNER → ADMIN or MEMBER
New member → OWNER
```

The profile deletion action must reject an Owner with a clear message until
all owned projects have been transferred. A removed/non-owner user is retained
or anonymized according to the history-retention rule in the database section.

---

# 39. Labels Collaboration Adjustment

Current labels are user-owned.

Collaborative projects should ultimately use **project-scoped labels**.

Target:

```text
labels.project_id
```

instead of relying exclusively on:

```text
labels.user_id
```

Why:

```text
Project: PlanOps
Labels: Backend, Frontend, Bug, Critical
```

All members should see the same label taxonomy.

## Sprint decision

Project-scoped labels are P0. Leaving labels user-owned would make the Admin
label permission and shared task filters inconsistent with the membership
model.

Migration rules:

- add nullable project_id first;
- infer a label's project from task_label attachments;
- duplicate the label per project when one legacy label spans projects;
- deduplicate by project_id and normalized_name;
- preserve the label name in task activity metadata when a merge occurs;
- keep unattached legacy labels private until a project is selected or archive
  them from project selectors;
- enforce unique(project_id, normalized_name) after backfill;
- make project_id required for every label exposed to collaboration; retain
  nullable private legacy rows only outside project selectors.

Required target before PlanOps 1.1 is considered fully complete:

- labels belong to project;
- Owner/Admin manage labels;
- Members can view labels;
- Members cannot create/delete labels.

---

# 40. Search Security Changes

Current search was designed for one user's owned objects.

New search rule:

> Search only resources whose project is accessible to the current user.

Never search globally and filter only after results are loaded.

Apply membership at query level.

Search result examples:

```text
PLAN-42   Invitation workflow    PlanOps
PORT-18   Pub/Sub ingestion      PortFlow
```

The user must have active membership in both projects.

---

# 41. Activity Feed Security Changes

Global activity should only expose events from projects the user can access.

Required scopes:

### Personal/global activity

```text
activities where project is accessible by current user
```

### Project activity

```text
activities where project_id = current project
AND current user is member
```

Activity itself may display actions by all team members.

---

# 42. Exports

Required permissions:

```text
Owner → full project export
Admin → full project export
Member → no full project export
```

Existing global exports must become membership-aware.

Never allow a user to export data for projects they cannot access.

Potential future Member export:

```text
Export my assigned tasks
```

is deferred.

---

# 43. Dashboard Changes

The personal dashboard should gradually shift from ownership metrics to responsibility metrics.

P0 collaboration-aware KPIs:

```text
Assigned to me
In progress
In review
Blocked
Due today
Overdue
```

Project cards may display:

```text
PlanOps
My tasks: 4
Team tasks: 18
Progress: 67%
```

For this sprint, prioritize correctness over adding many new dashboard charts.

---

# 44. Team Work View — Stretch / Next Sprint

A useful admin-oriented view can later show:

```text
TEAM WORK

Ali
  3 active
  1 overdue
  5 completed this week

Sara
  2 active
  0 overdue
  7 completed this week

Youssef
  1 blocked
  3 active
  4 completed this week
```

This should be treated as workload visibility, not employee ranking.

Do not build a “productivity score” per employee.

---

# 45. Notification Foundation

Collaboration makes notifications much more important.

Use Laravel's native notification system.

Planned P1 channels:

```text
Database
Email
```

Do not require WebSockets/Reverb in this sprint.

Database notifications are sufficient for an in-app notification center, and
email is sufficient for invitations/important assignment events once the
after-commit delivery contract is enabled. P0 must not claim that email was
delivered; it only records the event and preserves a usable pending invitation.

---

# 46. Notification Types

P1 notification types:

```text
ProjectInvitationCreated
TaskAssigned
TaskReassigned uses TaskAssigned with old/new assignee metadata
```

Member-removal notification is P2. The P0/P1 removal action still records the
project event and suppresses future notifications to the removed user.

Potential follow-up notifications:

```text
TaskCompleted
TaskBlocked
TaskDueSoon
MentionCreated
RoleChanged
```

Do not add all of these now.

---

# 47. Invitation Notification

Email content should communicate:

- inviter;
- project name;
- assigned role;
- expiration;
- acceptance CTA.

Example:

```text
Ali invited you to join PlanOps as a Member.

[Join project]

This invitation expires in 7 days.
```

Do not expose internal token hashes.

---

# 48. Assignment Notification

When Sara becomes assignee:

```text
Ali assigned PLAN-42 to you
Invitation workflow
Priority: High
Due: Sep 12

[Open task]
```

Avoid sending a duplicate notification when:

```text
old assignee == new assignee
```

Notification should be sent after the assignment transaction commits.

---

# 49. Notification Center UI

Navbar:

```text
PlanOps                         🔔 3     AH
```

Dropdown/page:

```text
NOTIFICATIONS

● Ali assigned PLAN-42 to you
  3 minutes ago

● You were added to PortFlow
  Yesterday

                     Mark all as read
```

Initial capabilities:

- unread count;
- latest notifications;
- mark one as read;
- mark all as read;
- link to target resource.

No realtime push is necessary initially.

---

# 50. Events for Decoupling

Prefer domain/application events where notification side effects would otherwise couple actions together.

Examples:

```text
ProjectInvitationCreated
ProjectInvitationAccepted
ProjectInvitationRevoked
TaskAssigned
ProjectMemberRemoved
ProjectMemberRoleChanged
ProjectOwnershipTransferred
```

Example flow:

```text
AssignTask
   ↓
DB transaction commits
   ↓
TaskAssigned event
   ↓
SendTaskAssignmentNotification listener
```

The task assignment action should remain correct even if notification delivery fails.

## 50.1 Notification delivery contract

Notification scope is intentionally split:

- P0 creates the invitation/assignment domain event and records the business
  outcome;
- P1 adds database notifications, unread/read state, and email delivery;
- WebSockets/Reverb remain out of scope.

Dispatch events after commit. Database notification writes and mail delivery
must not run inside the transaction that changes membership or assignment.
When a queue is enabled, notifications implement the Laravel after-commit
contract and use a bounded retry policy. When the queue is unavailable, the
business request still succeeds, the failure is logged with a correlation ID,
and the notification is retryable through failed_jobs and
php artisan queue:retry.

Notification payloads store snapshots and target IDs, not authorization
decisions. Every notification link reauthorizes the current viewer, and links
to a removed member's project must fail safely. Use idempotency keys such as
event ID plus recipient ID to prevent duplicate delivery on retries. Add
indexes for recipient/read state and created_at, and define a retention period
before enabling production delivery.

The repository currently starts queue:listen in composer dev scripts but
does not promise a permanent queue in the stack contract. Update composer,
environment, and stack documentation together; do not silently require a
worker for P0.

---

# 51. Transaction Boundaries

Use transactions for critical multi-record state changes.

Examples:

## Accept invitation

```text
validate invitation
create membership
mark invitation accepted
commit
notify/log afterwards
```

## Remove member

```text
lock membership
unassign active tasks
remove/deactivate membership
commit
```

## Assign task

```text
lock task
validate target membership
update assignee
record activity
commit
notify after commit
```

## Transfer ownership

```text
lock project
lock relevant memberships
validate new owner
change project.owner_id
old owner role change
new owner role change
commit
```

---

# 52. Concurrency Rules

Prevent race conditions around:

- two simultaneous invitations for same email/project;
- simultaneous assignment/reassignment;
- member removal while another admin assigns them work;
- simultaneous role changes;
- ownership transfer;
- project task numbering during collaborative task creation.

The current `CreateTask` already locks the project when allocating `next_task_number`.

Preserve this behavior so two Admins creating tasks simultaneously do not receive the same task number.

Refactor the lock query from:

```text
project belongs to current user
```

to:

```text
project accessible + current actor authorized to create tasks
```

---

# 53. Project Task Numbering

Keep existing stable task IDs:

```text
PLAN-1
PLAN-2
PLAN-3
```

Task creator/assignee changes must never change the task key.

This remains project-scoped identity.

---

# 54. Backward Compatibility

After migrations, a current single-user project should behave as if:

```text
existing user = Project OWNER
existing tasks = created by existing user
existing tasks = assigned to existing user
```

The user should not lose access or suddenly see an empty My Work screen.

All existing project/task URLs should continue working where possible.

---

# 55. UI Authorization Rules

The frontend should visually reflect permissions.

## Member task detail

Show:

```text
Status        editable IF assigned to me
Assignee      read-only
Priority      read-only
Due date      read-only
Title         read-only
Description   read-only
```

## Admin task detail

Show editable:

```text
Status
Assignee
Priority
Due date
Title
Description
Delete
```

## Owner project settings

Show:

```text
Members
Roles
Transfer ownership
Danger zone
```

But every backend route/action must independently authorize the operation.

UI hiding is not security.

---

# 56. Validation / Error UX

Use clear errors rather than generic 500s.

Examples:

```text
You do not have permission to assign tasks in this project.
```

```text
This person is no longer a member of the project.
```

```text
This task is assigned to another member.
```

```text
This invitation has expired.
```

```text
This invitation was already accepted.
```

```text
You cannot remove the project owner.
```

Where security disclosure matters, inaccessible resources may continue using `404` semantics instead of confirming that another user's project exists.

---

## 56.1 Route and UX contract

The collaboration routes must be explicit and covered by request tests:

| Method | Route | Ability / rule |
|---|---|---|
| GET | /projects/{project}/team | project.view |
| POST | /projects/{project}/invitations | project.invite |
| DELETE | /projects/{project}/invitations/{invitation} | project.invite; revoke only pending |
| POST | /projects/{project}/invitations/{invitation}/resend | project.invite |
| GET | /invitations/{token} | public preview; no project data |
| POST | /invitations/{token}/accept | authenticated email match; CSRF |
| PATCH | /projects/{project}/members/{user}/role | project.manageRoles |
| DELETE | /projects/{project}/members/{user} | project.manageMembers; never Owner |
| POST | /projects/{project}/ownership-transfer | project.transferOwnership |
| PATCH | /tasks/{task}/assignee | task.assign |
| POST | /tasks/{task}/restore | task.restore |
| GET | /notifications | authenticated notification list |
| PATCH | /notifications/{notification}/read | notification belongs to viewer |
| POST | /notifications/read-all | authenticated viewer only |

Use route names and controller methods consistently with the existing Laravel
route style. All state-changing routes require authentication and CSRF
protection. Invitation token routes are rate-limited and must not place raw
tokens in logs or rendered analytics.

UX requirements:

- show the viewer role and a reason when a control is read-only;
- use a keyboard-accessible assignee combobox with an Unassigned option;
- use cards rather than a wide table for the Team screen on small screens;
- provide visible focus, submit progress, validation errors, and focus return;
- use a live status message after invite, assignment, removal, or role changes;
- do not use icon-only controls without an accessible name;
- render inaccessible resources with the same 404/forbidden copy used by the
  backend contract;
- keep Analytics hidden from Members while retaining project progress in
  Overview.

---

# 57. Testing Strategy

The collaboration sprint needs strong authorization tests because most regressions will be security/permission related rather than syntax related.

Use Pest feature tests heavily.

## 57.1 Verification gates

Record the current baseline before the migration and require the full suite to
return zero failures before release. Existing unrelated failures must be
tracked by name and fixed or explicitly quarantined; a green collaboration
subset is not enough.

The CI test profile must use PostgreSQL for partial indexes, JSON metadata,
foreign-key behavior, and concurrency assertions. SQLite-only success does not
prove the collaboration contract.

Add request/feature coverage for every policy ability, every route in the
route table, archived/cancelled read-only behavior, account deletion guards,
cross-project selectors, exports, search, activity, dashboard, analytics, and
the migration backfill.

Add concurrency tests for duplicate invitations, concurrent acceptance,
assignment versus removal, role changes, ownership transfer, and task number
allocation. Assert both the final rows and the absence of duplicate activity
or notification side effects.

The UI gate includes browser tests for invite/accept, Team management,
assignment, Member status editing, forbidden edits, notification read state,
keyboard-only navigation, focus return, visible validation, and 404/403
states. Use Playwright and axe only if the dependency/configuration is added
to the repository; otherwise remove the promise from the stack contract before
release.

---

# 58. Membership Tests

Test:

- project creator receives OWNER membership;
- existing projects receive OWNER memberships during migration/seeding strategy;
- duplicate membership is rejected;
- Member can view project;
- non-member cannot view project;
- removed member immediately loses project access;
- member role is project-scoped;
- same user can have different roles in two projects.

---

# 59. Invitation Tests

Test:

- Owner can invite Member;
- Admin can invite Member;
- Member cannot invite;
- Admin cannot invite Admin if Owner-only elevation rule is used;
- cannot invite existing member;
- cannot create duplicate pending invite;
- valid invite can be accepted;
- expired invite cannot be accepted;
- revoked invite cannot be accepted;
- accepted invite cannot be reused;
- authenticated email must match invited email;
- acceptance creates exactly one membership;
- acceptance marks invite accepted;
- resend rotates/refreshes token safely;
- raw token is not stored.

---

# 60. Task Authorization Tests

Test Owner:

- can create;
- can update;
- can change status;
- can change priority;
- can change due date;
- can assign;
- can delete/restore.

Test Admin:

- same operational task capabilities as Owner.

Test Member:

- cannot create;
- cannot update title;
- cannot update description;
- cannot change priority;
- cannot change due date;
- cannot assign;
- cannot delete;
- can change status of own assigned task;
- cannot change status of another member's task;
- cannot change status of unassigned task.

Test non-member:

- cannot read or mutate any project task.

---

# 61. Assignment Tests

Test:

- Owner can assign Member;
- Admin can assign Member;
- Member cannot assign;
- cannot assign non-member;
- cannot assign user from another project;
- can unassign task;
- can reassign task;
- no-op assignment creates no duplicate activity;
- assignment creates actor-aware activity;
- assignment notification goes to new assignee;
- old assignee behavior for reassignment is defined and tested;
- removed member's tasks become unassigned.

---

# 62. Route Security Tests

Test direct URL manipulation.

Example:

```text
User belongs to Project A
User does not belong to Project B
```

Ensure they cannot access:

```text
/projects/B
/projects/B/tasks
/projects/B/board
/projects/B/analytics
/tasks/{B-task}
```

Also test nested mismatch:

```text
/projects/A/board/tasks/{task-from-B}/status
```

must be rejected.

---

# 63. My Work Tests

Test:

- shows tasks assigned to current user;
- includes tasks from multiple accessible projects;
- excludes tasks created by user but assigned to someone else;
- excludes tasks in projects where membership was removed;
- respects status/priority/due filters;
- preserves current pagination/sorting behavior;
- supports migrated legacy tasks.

---

# 64. Activity Tests

Test:

- activity actor is authenticated user;
- actor differs correctly from assignee/creator;
- member status transition records Member as actor;
- admin assignment records Admin as actor;
- invitation, role, removal, and ownership changes create immutable project_events;
- append-only protection remains;
- removed users' historical activity remains readable to authorized project members;
- inaccessible-project activity never leaks into global feed.

---

# 65. Notification Tests

Use Laravel notification fakes where appropriate.

Test:

- invitation sends intended notification;
- assignment sends notification to new assignee;
- no-op assignment sends nothing;
- notifications are dispatched only after the business transaction commits;
- a delivery failure does not roll back an accepted invite or assignment;
- unauthorized action sends nothing;
- notification references accessible task/project;
- database notification can be marked read;
- read/unread count works.

---

# 66. Analytics Considerations

Do not attempt full team analytics in the same sprint unless core collaboration is complete.

However, current analytics queries must become membership-aware.

Important pre-existing analytics concern to keep on the backlog:

> Time-in-status calculations should correctly account for the state a task was already in at the beginning of the reporting period.

Collaboration must not build richer analytics on top of incorrect period-boundary semantics.

A later analytics sprint can introduce:

- workload distribution;
- tasks completed by assignee;
- active tasks per member;
- overdue tasks per member;
- blocked tasks per member;
- cycle time by project;
- unassigned work.

Do **not** introduce employee ranking/productivity scoring.

## 66.1 Analytics and export privacy contract

Every report accepts an explicit UTC time range plus the viewer's display
timezone. The selected range is shown in the UI and stored nowhere as a
server-global default.

Overview progress is visible to active project members. Detailed analytics and
complete exports are Owner/Admin capabilities. Global analytics and exports
must filter each project by the corresponding policy ability rather than
filtering after rows are loaded.

For time-in-status, initialize a period from the last status event at or
before the range start. If no prior event exists, label the duration as
unknown rather than counting the whole period as NOT_STARTED. Add a test for
the task that entered IN_PROGRESS before the selected range.

The personal dashboard means assigned-to-me work across accessible projects.
It must not become a cross-team productivity score or expose member-level
performance rankings.

---

# 67. Sprint Backlog — P0 Must Have

Execution tracking is split into the issue-ready backlog files in [`docs/backlogs/README.md`](backlogs/README.md). The files preserve the DYX-000–DYX-007 dependency order, separate P0/P1/P2 scope, and repeat the acceptance and verification contract needed for implementation. This section remains the authority; the backlog files are the working index.

## EPIC A — Collaboration data model

- [ ] Create `ProjectRole` enum.
- [ ] Create `ProjectMembership` model/migration.
- [ ] Create `ProjectInvitation` model/migration.
- [ ] Add/backfill `projects.owner_id`.
- [ ] Add/backfill `tasks.created_by_user_id`.
- [ ] Add/backfill `tasks.assignee_id`.
- [ ] Add/backfill activity actor field.
- [ ] Create immutable project_events for membership/security changes.
- [ ] Create existing OWNER memberships.
- [ ] Update model relationships.
- [ ] Add active-membership, one-active-Owner, invitation, label, and global
  project-key constraints.
- [ ] Migrate labels to project scope and resolve duplicate normalized names.

## EPIC B — Authorization/access

- [ ] Implement `Project::accessibleBy()`.
- [ ] Implement task membership-aware access.
- [ ] Replace route binding `ownedBy()` assumptions.
- [ ] Redesign `ProjectPolicy`.
- [ ] Redesign `TaskPolicy`.
- [ ] Add dedicated `changeStatus` permission.
- [ ] Add dedicated `assign` permission.
- [ ] Add viewAnalytics/export abilities and audit every query/controller scope.
- [ ] Protect nested project/task routes.

## EPIC C — Member management

- [ ] Project Team/Members page.
- [ ] Invite Member flow.
- [ ] Accept invitation flow.
- [ ] Revoke invitation.
- [ ] Resend invitation.
- [ ] Remove Member.
- [ ] Owner protection.
- [ ] Transfer ownership atomically before allowing Owner account deletion.
- [ ] Owner role-management UI for Member/Admin.

## EPIC D — Task assignment

- [ ] Add assignee field to task domain.
- [ ] Create `AssignTask`/`ChangeTaskAssignee` action.
- [ ] Assignee must be active project member.
- [ ] Assign/reassign/unassign.
- [ ] Show assignee on task detail/list/board.
- [ ] Add assignee selector for Owner/Admin.
- [ ] Read-only assignee for Member.
- [ ] Add assignment activity event.
- [ ] Revalidate active membership inside the locked transaction.

## EPIC E — My Work

- [ ] Rewrite My Work around `assignee_id`.
- [ ] Support assigned tasks across multiple projects.
- [ ] Preserve existing filters/sorting.
- [ ] Verify membership before displaying task.

## EPIC F — Activity

- [ ] Actor-aware `TaskActivityRecorder`.
- [ ] Update all existing task actions to pass actor.
- [ ] Render actor names in activity feed.
- [ ] Maintain append-only history.
- [ ] Test project_events for invite, role, removal, and transfer actions.

## EPIC G — Tests

- [ ] Membership tests.
- [ ] Invitation tests.
- [ ] Role/policy tests.
- [ ] Assignment tests.
- [ ] Member status tests.
- [ ] Cross-project access tests.
- [ ] My Work tests.
- [ ] Activity actor tests.
- [ ] Migration, PostgreSQL, concurrency, export, analytics, and browser/a11y
  gates.

---

# 68. Sprint Backlog — P1 Should Have

- [ ] Database notifications after commit.
- [ ] Invitation and assignment email delivery with bounded retries.
- [ ] Notification bell/unread count.
- [ ] Notification center/list.
- [ ] Mark read / mark all read.
- [ ] Assignee filters on project task list.
- [ ] Assignee filters on board.
- [ ] Collaboration-aware export authorization.
- [ ] Collaboration-aware dashboard counts.
- [ ] Invitation pending state UI.

---

# 69. Sprint Backlog — P2 Stretch

- [ ] Team Work screen.
- [ ] Role-change activity feed.
- [ ] Member-removal notification.
- [ ] Team analytics first version.
- [ ] Realtime notification delivery.

If P0 work is not fully secure/tested, P2 must not be started.

---

# 70. Suggested Implementation Order

The implementation sequence matters because later features depend on earlier authorization/data work.

## Phase 1 — Schema + domain foundation

```text
ProjectRole
ProjectMembership
ProjectInvitation
owner_id
created_by_user_id
assignee_id
actor_user_id
project_events
project-scoped labels
```

## Phase 2 — Migration/backfill

Run the preflight report, additive migrations, batched backfill, duplicate-key
resolution, invariant validation, and rollback rehearsal.

## Phase 3 — Access layer

```text
accessibleBy()
ProjectPolicy
TaskPolicy
route bindings
```

At this point, existing application behavior should still work.

## Phase 4 — Invitation/member management

```text
Invite
Accept
Revoke
Resend
Remove
Role management
Ownership transfer
```

## Phase 5 — Assignment

```text
AssignTask
assignee UI
assignment activity
```

## Phase 6 — My Work rewrite

Make assigned work the personal execution surface.

## Phase 7 — Notifications

Dispatch domain events after commit. Add database notifications and email in
P1 with idempotency keys, retry logging, and target reauthorization.

## Phase 8 — Full regression/security test pass

Run the full Pest suite on PostgreSQL, concurrency tests, browser/axe/keyboard
checks, migration verification, build verification, and documentation
reconciliation. Do not release with a partially migrated authorization layer.

---

## 70.1 DYX task sequence

Each task is independently reviewable. Do not start a task until its dependency
is green.

### Task DYX-000

Goal: Freeze the collaboration contract and record the current baseline.

Files: `docs/PlanOps_Sprint_2.md`, `docs/architecture/domain-contracts.md`,
`docs/architecture/stack.md`, `docs/ui/screen-spec.md`,
`planops-complete-spec.md`, and the existing implementation plan.

Action: compare the baseline documents with this plan; record current Pest,
build, and route-list results; align the queue, browser, and accessibility
promises.

Why: later security work must not be judged against contradictory contracts
or an unknown failing-test baseline.

Verification: `php artisan test`, `npm.cmd run build`, and
`php artisan route:list --except-vendor` are recorded before implementation.

Expected result: one accepted baseline and one authoritative Sprint 2 contract.

### Task DYX-001

Goal: Add collaboration invariants and migrate existing data safely.

Files: `database/migrations/`,
`app/Domain/Collaboration/Models/ProjectMembership.php`,
`app/Domain/Collaboration/Models/ProjectInvitation.php`,
`app/Domain/Projects/Models/Project.php`, `app/Domain/Tasks/Models/Task.php`,
`database/factories/`, `database/seeders/`, and migration feature tests.

Action: add owner_id, memberships, invitations, project_events, creator,
assignee, actor, normalized email, removed_at, project-scoped labels, and
global project-key constraints; backfill in batches; validate counts and
orphan reports; keep legacy columns until cutover.

Why: policies cannot be trusted while ownership and referential invariants
are only conventions.

Verification: run the migration twice on a PostgreSQL database and assert
exactly one active OWNER, owner/membership agreement, valid assignees, stable
task keys, and preserved project/task/activity counts.

Expected result: old personal projects remain accessible as Owner projects.

Dependency: DYX-000.

### Task DYX-002

Goal: Replace owner-only access with one membership-aware authorization layer.

Files: `app/Domain/Projects/Models/Project.php`,
`app/Domain/Tasks/Models/Task.php`, `app/Policies/ProjectPolicy.php`,
`app/Policies/TaskPolicy.php`, `app/Policies/LabelPolicy.php`,
`routes/web.php`, all listed query classes,
`app/Http/Requests/ReorderTasksRequest.php`,
`app/Domain/Tasks/Actions/ReorderTasks.php`, and
`app/Http/Controllers/ExportController.php`.

Action: implement accessibleBy(viewer), explicit policy abilities, nested
binding checks, archived read-only behavior, export/viewAnalytics checks,
and transaction-time revalidation. Remove every security-critical ownedBy()
caller.

Why: direct URLs, selectors, exports, and mutation actions are all part of
the attack surface.

Verification: request tests cover each role, every route, cross-project task
mismatch, removed membership, archived project, search, activity, analytics,
dashboard, and export scoping.

Expected result: no project/task data is returned or mutated without active
membership and the required ability.

Dependency: DYX-001.

### Task DYX-003

Goal: Deliver the member and invitation lifecycle.

Files: `app/Domain/Collaboration/Actions/`,
`app/Domain/Collaboration/Rules/`, invitation/member controllers and requests,
`resources/views/`, `routes/web.php`, and invitation/membership feature tests.

Action: implement invite, public token preview, authenticated acceptance,
resend, revoke, remove, role change, and atomic ownership transfer with
canonical email matching, rate limits, CSRF, token hashing, unique pending
invites, and retained audit events.

Why: membership is the security boundary and ownership transfer closes the
profile-deletion escape gap.

Verification: test duplicate/racing invites, expired/revoked/reused tokens,
email mismatch, concurrent acceptance, role restrictions, owner protection,
removal/unassignment, and no project-data disclosure on token preview.

Expected result: members gain or lose access immediately and exactly one
membership is created for a successful acceptance.

Dependency: DYX-002.

### Task DYX-004

Goal: Add assignment, actor-aware task changes, and assignment-based My Work.

Files: `app/Domain/Tasks/Actions/AssignTask.php` or
`ChangeTaskAssignee.php`, `app/Domain/Activity/Services/TaskActivityRecorder.php`,
`app/Domain/Activity/Models/TaskActivity.php`, task actions,
`app/Domain/Tasks/Queries/MyWorkQuery.php`, task views, and feature tests.

Action: add one nullable assignee, actor-aware recorder calls,
ASSIGNEE_CHANGED, non-CANCELLED Member status transitions, locked
assignment/removal races, and accessible assigned-task queries.

Why: collaboration is useful only when responsibility and history are both
correct.

Verification: test assign/reassign/unassign, no-op suppression, actor IDs,
Member status boundaries, removed-member unassignment, and My Work across
multiple projects.

Expected result: every task mutation identifies the actor and My Work shows
only assigned tasks in accessible projects.

Dependency: DYX-003.

### Task DYX-005

Goal: Make labels project-scoped without leaking or merging unrelated data.

Files: label migrations, `app/Domain/Labels/Models/Label.php`, label actions
and policies, task-label queries, label views, and label tests.

Action: infer projects from task attachments, duplicate cross-project legacy
labels, deduplicate by normalized name within each project, and restrict
management to Owner/Admin.

Why: shared task filters require a shared project taxonomy.

Verification: test cross-project duplicate names, unattached legacy labels,
member read access, and Owner/Admin mutation boundaries.

Expected result: project members see one consistent label set for their project.

Dependency: DYX-001 and DYX-002.

### Task DYX-006

Goal: Add notification delivery after the P0 business outcomes are stable.

Files: notification migrations/models, notification classes/listeners,
`composer.json`, `.env.example`, notification views/routes, and notification
feature tests.

Action: add database notifications and email after commit with idempotency
keys, bounded retries, failure logging, read state, retention, and target
reauthorization. Keep WebSockets out of scope.

Why: delivery failures must not roll back invitations or assignments.

Verification: fake notifications/mail, assert after-commit behavior, retry
deduplication, read/unread counts, removed-member suppression, and accessible
target links.

Expected result: P1 delivery is observable and retryable without changing the
P0 authorization outcome.

Dependency: DYX-003 and DYX-004.

### Task DYX-007

Goal: Close the release gate and reconcile all contracts.

Files: `tests/Feature/`, `tests/Browser/`, Playwright/axe configuration if
adopted, `docs/architecture/`, `docs/ui/`, and the Sprint 2 document.

Action: run the full PostgreSQL Pest suite, migration rehearsal, concurrency
suite, browser and keyboard checks, `npm.cmd run build`, and documentation
searches for stale ownedBy(), user_id ownership, route, queue, and label claims.

Why: the highest-risk failures are silent access leaks and documentation drift.

Verification: zero unapproved failures, zero skipped security/concurrency
tests, successful build, accepted migration report, and synchronized contracts.

Expected result: Sprint 2 is releasable or has a named, blocking reason.

Dependency: DYX-005 and DYX-006.

### Next action

Start with DYX-000: capture the baseline and synchronize the authority documents
before writing collaboration migrations.

---
# 71. Definition of Done

The sprint is considered complete only if all of the following are true.

## Membership

- [ ] Every project has exactly one Owner.
- [ ] Existing project owners were migrated without losing access.
- [ ] A user may be Member/Admin/Owner depending on the project.
- [ ] A non-member cannot access project data by URL/query.
- [ ] Exactly one active Owner is enforced by database and transaction checks.
- [ ] Owner transfer is atomic and required before Owner account deletion.
- [ ] Removed memberships remain auditable and cannot access project data.

## Invitations

- [ ] Owner/Admin can invite according to policy.
- [ ] Invitation has secure expiring token.
- [ ] Invite can be accepted exactly once.
- [ ] Duplicate/existing-member invitations are prevented.
- [ ] Invitation acceptance creates membership atomically.
- [ ] Canonical email, partial uniqueness, rate limits, CSRF, and no raw-token
  logging are verified.

## Roles

- [ ] OWNER/ADMIN/MEMBER behavior matches permission matrix.
- [ ] Member cannot mutate task content/priority/due date/assignment.
- [ ] Member can change status only on their assigned task.
- [ ] Members cannot set CANCELLED.
- [ ] Role checks are enforced server-side.

## Assignment

- [ ] Task has zero or one assignee.
- [ ] Assignee must belong to project.
- [ ] Owner/Admin can assign/reassign/unassign.
- [ ] Member removal safely unassigns work.
- [ ] Assignment is visible on board/list/detail.
- [ ] Assignment/removal races leave no task assigned to an inactive member.

## My Work

- [ ] My Work means assigned-to-me across accessible projects.
- [ ] Existing personal tasks remain visible after migration.

## Activity

- [ ] Activity records actor separately from creator/assignee.
- [ ] Assignment/status changes show correct actor.
- [ ] Historical activity remains append-only.
- [ ] Membership/security changes are present in immutable project_events.

## Security

- [ ] Cross-project crafted URLs are rejected.
- [ ] Search/activity/analytics/export do not leak inaccessible project data.
- [ ] Nested routes verify task belongs to project.
- [ ] Archived/cancelled projects reject all writes except restore.
- [ ] Profile deletion cannot destroy creator, assignee, or actor history.

## Testing

- [ ] Existing relevant tests pass after refactor.
- [ ] New policy/authorization tests exist for all three roles.
- [ ] Invitation and assignment happy/error paths are covered.
- [ ] PostgreSQL migration and concurrency gates pass.
- [ ] Browser, keyboard, and accessibility gates pass when promised by stack.
- [ ] Frontend production build passes.
- [ ] Architecture/UI/spec documents contain no stale ownership or route claims.

---

# 72. Acceptance Scenarios

## Scenario A — Create collaborative project

```gherkin
Given Ali is authenticated
When Ali creates project "PlanOps"
Then Ali becomes the project Owner
And an OWNER membership exists for Ali
```

## Scenario B — Invite Member

```gherkin
Given Ali owns PlanOps
When Ali invites sara@example.com as Member
Then a pending invitation is created
And the invitation delivery event is recorded after commit
And P1 email delivery may notify Sara when enabled
```

## Scenario C — Accept invitation

```gherkin
Given Sara has a valid pending PlanOps invitation
When Sara authenticates with the invited email and accepts
Then Sara becomes a MEMBER of PlanOps
And the invitation becomes accepted
And Sara can view PlanOps
```

## Scenario D — Admin assigns task

```gherkin
Given Ali is Owner
And Sara is a Member of PlanOps
And PLAN-42 is unassigned
When Ali assigns PLAN-42 to Sara
Then PLAN-42.assignee is Sara
And assignment activity records Ali as actor
And Sara receives an assignment notification
```

## Scenario E — Member updates assigned task status

```gherkin
Given Sara is a Member
And PLAN-42 is assigned to Sara
When Sara moves PLAN-42 from NOT_STARTED to IN_PROGRESS
Then the status changes
And activity records Sara as actor
```

## Scenario F — Member attempts forbidden edit

```gherkin
Given Sara is a Member
And PLAN-42 is assigned to Sara
When Sara attempts to change PLAN-42 priority
Then the request is forbidden
And the task remains unchanged
```

## Scenario G — Member touches another user's task

```gherkin
Given PLAN-43 is assigned to Youssef
When Sara attempts to change PLAN-43 status
Then the request is forbidden
```

## Scenario H — Removed member

```gherkin
Given Sara is a Member
And PLAN-42 is assigned to Sara
When an authorized project manager removes Sara
Then Sara loses access to PlanOps
And PLAN-42 becomes unassigned
And historical activity created by Sara remains visible to authorized project members
```

## Scenario I — My Work

```gherkin
Given Sara belongs to PlanOps and PortFlow
And PLAN-42 is assigned to Sara
And PORT-18 is assigned to Sara
When Sara opens My Work
Then PLAN-42 and PORT-18 appear
And tasks assigned to other people do not appear
```

---

## 72.1 Security and lifecycle scenarios

### Scenario J - Owner transfer protects account deletion

~~~gherkin
Given Ali owns PlanOps and Sara is an active Member
When Ali transfers ownership to Sara
Then Sara becomes the only active OWNER
And Ali becomes an ADMIN
And the transfer is recorded in project_events
And Ali can no longer delete the project as Owner
~~~

### Scenario K - Removed membership is immediate

~~~gherkin
Given Sara is assigned PLAN-42
When an authorized manager removes Sara
And Sara submits a status change at the same time
Then the final task has no inactive assignee
And Sara's status request is forbidden
And only one removal/unassignment history is recorded
~~~

### Scenario L - Archived projects are read-only

~~~gherkin
Given PlanOps is archived
When a Member opens the project
Then project history and Overview progress remain visible
When any user attempts to create, edit, assign, reorder, or invite
Then the request is rejected
And restore is available only to Owner/Admin
~~~

### Scenario M - Invitation token preview is private

~~~gherkin
Given a valid invitation token exists
When an unauthenticated visitor opens the token URL
Then no project name, member list, or account-existence detail is disclosed
When an authenticated user accepts with a different canonical email
Then acceptance is rejected and no membership is created
~~~

---
# 73. Architecture After Sprint

```text
Identity
│
├── User
└── Preferences

Collaboration
│
├── ProjectMembership
├── ProjectInvitation
└── ProjectRole

Projects
│
└── Project
    ├── Owner
    ├── Members
    ├── Labels
    └── Tasks

Tasks
│
├── Task
├── Subtask
├── Creator
├── Assignee
├── Status
├── Priority
└── Due Date

Activity
│
├── Actor
├── Task Events
├── Status Events
├── Assignment Events
└── Project Events

Personal Work
│
└── My Work
    └── Assigned to current user

Notifications
│
├── Invitation
└── Assignment

Analytics
│
├── Personal
└── Project
```

---

# 74. Roadmap After This Sprint

## PlanOps 1.1 — Collaboration Foundation

This sprint:

```text
Project membership
Invitations
OWNER / ADMIN / MEMBER
Project-scoped authorization
Assignees
Actor-aware activity
Assignment-based My Work
Project-scoped labels
Minimal ownership transfer
Notification foundation
```

## PlanOps 1.2 — Collaboration Experience

Next:

```text
Notification center polish
Team Work
Team analytics
Saved views by assignee
Better workload visibility
```

## PlanOps 1.3 — Planning Workspace

Then:

```text
Today
Tomorrow
This Week
DailyPlan
DailyPlanItem
Top 3
Planned duration
Carry-forward
```

This becomes much more valuable after assignment because:

> **Today = the work assigned to me that I intend to execute today.**

## PlanOps 1.4

```text
Recurring tasks
Saved Views
Quick Add
Keyboard shortcuts
Command palette
```

## PlanOps 1.5

```text
Team/project analytics
Project health
Workload visibility
```

## PlanOps 1.6

```text
Calendar
Timeboxing
Google Calendar
Outlook
```

## PlanOps 2.x

```text
AI weekly summaries
Task decomposition
Planning suggestions
Risk detection
Natural-language search
Integrations/API
```

---

# 75. Important Design Decisions Locked by This Sprint

1. **Roles are project-scoped.**
2. **Three roles only:** OWNER, ADMIN, MEMBER.
3. **Exactly one active Owner per project, enforced by a partial unique index and a locked transaction.**
4. **One assignee per task.**
5. **Assignee must be an active project member.**
6. **Member can change only a non-CANCELLED status on their own assigned tasks.**
7. **Owner/Admin manage task content and assignments.**
8. **Membership is the access/security boundary.**
9. **Assignment is responsibility, not authorization to access foreign projects.**
10. **My Work becomes assignment-based.**
11. **Activity records the actor.**
12. **Historical activity survives member removal.**
13. **Removed members' assigned tasks become unassigned.**
14. **Raw invitation tokens are never stored.**
15. **No customizable RBAC in this phase.**
16. **No multiple assignees in this phase.**
17. **No realtime/WebSockets required in this phase.**
18. **Daily Planning is deferred until collaboration is stable.**
19. **Removed memberships are retained with removed_at and remain auditable.**
20. **Owner transfer is P0; Owner account deletion is blocked until transfer.**
21. **Project keys are globally unique for stable task identifiers.**
22. **Labels are project-scoped in P0.**
23. **Members see Overview progress/activity but not detailed team analytics or complete exports.**
24. **Invitation acceptance is P0; database/email delivery is P1 and runs after commit.**
25. **Archived/cancelled projects are read-only except for Owner/Admin restore.**

---

# 76. Engineering Risks

## Risk 1 — Hidden ownership assumptions

The current application repeatedly uses `user_id` and `ownedBy()`.

Mitigation:

- search the full repository for `user_id`, `ownedBy`, and direct owner comparisons;
- migrate queries systematically;
- add cross-project security tests.

## Risk 2 — Authorization regression

A Member may accidentally inherit general update rights.

Mitigation:

- separate policy abilities (`update`, `changeStatus`, `assign`, etc.);
- test each role/action matrix explicitly.

## Risk 3 — Route-model leakage

Generic model binding could expose tasks from inaccessible projects.

Mitigation:

- membership-aware binding;
- nested task/project checks;
- 404/403 feature tests.

## Risk 4 — Migration data loss

Renaming/removing `user_id` too early could break historical data.

Mitigation:

- additive columns;
- backfill;
- application cutover;
- only then remove legacy columns.

## Risk 5 — Notification coupling

Email failures should not roll back valid business operations.

Mitigation:

- commit business transaction first;
- dispatch event/notification after commit;
- queue mail in P1 under the documented worker and retry contract.

## Risk 6 — Sprint overload

Collaboration touches most application queries.

Mitigation:

- treat P0 as the true sprint boundary;
- notification polish/team analytics remain P1/P2;
- do not begin Daily Planning in this sprint.

## Risk 7 — Invariant drift under concurrent writes

Two requests can otherwise create two active Owners, accept one invitation
twice, or assign work to a member being removed.

Mitigation:

- enforce partial unique indexes where PostgreSQL supports them;
- lock project, invitation, membership, and task rows in a fixed order;
- revalidate policy inputs inside each transaction;
- test the race, not only the happy path.

## Risk 8 — Contract and tooling drift

The current stack documentation, route map, queue promise, and browser-test
promise do not fully match the repository.

Mitigation:

- make DYX-000 a release prerequisite;
- search for stale ownedBy(), owner user_id, route, label, queue, and
  notification claims before merge;
- either add Playwright/axe and a queue contract or remove those promises.

---

# 77. Current Best-Practice References Used for This Design

The following current product/framework references were used to validate the proposed direction.

## Laravel

### Laravel 13 — Directory / architectural conventions

Laravel documents Policies as the application layer used to determine whether a user may perform an action against a resource, Notifications as transactional notifications, and Events/Listeners as a decoupling mechanism.

- https://laravel.com/docs/13.x/structure
- https://laravel.com/framework/docs/events
- https://api.laravel.com/docs/13.x/Illuminate/Notifications.html

### Laravel Notifications

Laravel notifications support database-backed and mail-style delivery patterns. Database notifications provide a natural implementation path for the PlanOps notification center.

- https://laravel.com/docs/12.x/notifications

## Linear

### Members and roles

Linear separates administrative roles from standard members and preserves suspended/removed users in historical issue context.

- https://linear.app/docs/members-roles

### Invitations

Linear supports explicit invitations, pending users, role selection, and member administration.

- https://linear.app/docs/invite-members

### Single assignee

Linear assigns an issue to one person at a time to maintain clear ownership/responsibility and surfaces assigned issues in a personal work view.

- https://linear.app/docs/assigning-issues

## Atlassian / Jira

Jira uses project-scoped roles/permissions and distinguishes the permission to assign work from whether a person is eligible to be assigned.

- https://support.atlassian.com/jira/kb/jira-permissions-general-overview/
- https://support.atlassian.com/jira-cloud-administration/docs/work-item-permissions/
- https://support.atlassian.com/platform-experiences/docs/set-up-permissions-for-projects/

These products are references for established collaboration patterns, not feature checklists PlanOps must copy.

---

# 78. Final Sprint Principle

The objective is **not** to make PlanOps “Jira-lite” by adding every collaboration feature.

The objective is to establish a clean collaboration contract:

```text
Who can access the project?
        ↓
What role do they have?
        ↓
Who owns each task?
        ↓
What is each role allowed to change?
        ↓
Who performed each action?
        ↓
How does each person see their own work?
```

If those six questions are modeled correctly, PlanOps will have a strong foundation for every later feature: Daily Planning, reminders, calendar integration, team analytics, dependencies, milestones, integrations, and AI assistance.

The most important outcome of this sprint is therefore not the Invite button itself. It is the transition from **single-user ownership** to a secure, explicit, testable **collaborative project model**.
