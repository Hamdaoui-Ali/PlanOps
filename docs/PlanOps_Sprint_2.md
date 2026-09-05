# PlanOps — Next Sprint Specification
## Collaborative Projects, Groups/Teams, Roles, Invitations, Task Assignment & Notifications

**Project:** PlanOps  
**Target stack:** PHP 8.3+, Laravel 13, Blade/Tailwind, PostgreSQL-compatible persistence, Pest 4  
**Sprint type:** Architectural feature sprint  
**Primary release target:** `PlanOps 1.1 — Collaboration Foundation`  
**Secondary/stretch target:** first slice of `PlanOps 1.2 — Assignment & Notifications`

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
10. Add the first notification foundation for invitations and assignments.

## Quality goals

1. Preserve existing project/task behavior for single-user users.
2. Preserve existing historical task/activity data.
3. Avoid security regressions caused by route-model binding or direct URLs.
4. Avoid global roles on the `users` table.
5. Keep role logic centralized in policies/domain logic.
6. Keep one human assignee per task.
7. Maintain test coverage for every permission boundary.

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
- archive/restore project if desired by the final policy;
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
- promote/demote Admin roles unless explicitly allowed later.

### Recommended security boundary

Only the Owner manages role elevation:

```text
MEMBER ↔ ADMIN
```

This prevents one Admin from silently expanding administrative authority.

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
| Invite Admin | ✅ | Recommended: ❌ | ❌ |
| Remove Member | ✅ | ✅ | ❌ |
| Change Member/Admin role | ✅ | ❌ | ❌ |
| Edit project | ✅ | ✅ | ❌ |
| Archive/restore project | ✅ | ✅ | ❌ |
| Transfer ownership | ✅ | ❌ | ❌ |
| Delete project permanently | ✅ | ❌ | ❌ |
| Export complete project data | ✅ | ✅ | ❌ |
| Team analytics | ✅ | ✅ | Limited/❌ |

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
created_at
updated_at
```

Constraints:

```text
UNIQUE(project_id, user_id)
```

Indexes:

```text
(project_id)
(user_id)
(project_id, role)
```

Foreign keys:

```text
project_id → projects.id
user_id    → users.id
```

Recommended delete behavior:

- deleting a project may cascade membership deletion;
- deleting a user account needs an explicit product decision and should not silently destroy project history.

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

### Recommended choice

For existing personal tasks, set:

```text
assignee_id = existing user_id
```

because the previous single user was effectively responsible for all work.

This means existing My Work behavior remains intuitive immediately after migration.

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
role
invited_by_user_id
token_hash
expires_at
accepted_at
revoked_at
created_at
updated_at
```

Recommended constraints/indexes:

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

at the domain/service level.

---

# 15. Invitation Status

Do not necessarily persist a `status` column if the status can be derived cleanly.

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

If a persisted enum is preferred for query ergonomics, then enforce transitions in one domain action/service.

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

Recommended initial product rule:

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
Send notification/email
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
4. Recommended: only Owner may invite someone directly as Admin.
5. Member invitation role defaults to `MEMBER`.
6. Invitation acceptance must match the authenticated user email.
7. Expired invitations cannot be accepted.
8. Revoked invitations cannot be accepted.
9. Accepted invitations cannot be accepted again.
10. Resend should invalidate the previous token or update expiry/token safely.
11. Email comparison should be normalized case-insensitively.

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

Recommended actions:

```text
InviteProjectMember
AcceptProjectInvitation
RevokeProjectInvitation
ResendProjectInvitation
RemoveProjectMember
ChangeProjectMemberRole
TransferProjectOwnership
```

Stretch if sprint capacity is limited:

```text
TransferProjectOwnership
```

may be deferred to the next sprint, provided the Owner cannot leave/remove themselves.

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

Recommended semantics:

```text
owner()       → belongsTo(User::class, 'owner_id')
memberships() → hasMany(ProjectMembership::class)
members()     → belongsToMany(User::class, 'project_memberships')
```

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

Recommended meanings:

```text
ownedProjects()      → projects where owner_id = user.id
projectMemberships() → membership rows
projects()           → projects accessible through membership
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

Recommended policy abilities:

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
```

---

# 26. TaskPolicy Redesign

This is one of the most important sprint changes.

Create explicit abilities instead of using generic `update` for everything.

Recommended abilities:

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

Recommended flow:

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

Add optional future filters:

```text
Assigned to me
Unassigned
```

for admin project views, not necessarily global My Work.

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

Recommended screen:

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

---

# 38. Owner Protection

The Owner cannot simply remove themselves or leave the project.

Reject:

```text
Owner → Leave project
```

unless ownership has first been transferred.

Target future flow:

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

If ownership transfer is outside current sprint capacity, enforce:

```text
Owner cannot leave/remove self
```

and defer transfer UI/action.

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

If collaboration scope becomes too large, project-scoped label migration may be a **P1 stretch item**, but every label access query must at minimum remain secure.

Recommended target before PlanOps 1.1 is considered fully complete:

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

Recommended scopes:

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

Recommended permissions:

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

Recommended collaboration-aware KPIs:

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

Initial channels:

```text
Database
Email
```

Do not require WebSockets/Reverb in this sprint.

Database notifications are sufficient for an in-app notification center, and email is sufficient for invitations/important assignment events.

---

# 46. Notification Types

Recommended first notifications:

```text
ProjectInvitationCreated
TaskAssigned
TaskReassigned (optional, can reuse TaskAssigned)
ProjectMemberRemoved (optional)
```

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
ProjectMemberInvited
ProjectInvitationAccepted
TaskAssigned
ProjectMemberRemoved
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

# 57. Testing Strategy

The collaboration sprint needs strong authorization tests because most regressions will be security/permission related rather than syntax related.

Use Pest feature tests heavily.

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

---

# 67. Sprint Backlog — P0 Must Have

## EPIC A — Collaboration data model

- [ ] Create `ProjectRole` enum.
- [ ] Create `ProjectMembership` model/migration.
- [ ] Create `ProjectInvitation` model/migration.
- [ ] Add/backfill `projects.owner_id`.
- [ ] Add/backfill `tasks.created_by_user_id`.
- [ ] Add/backfill `tasks.assignee_id`.
- [ ] Add/backfill activity actor field.
- [ ] Create existing OWNER memberships.
- [ ] Update model relationships.
- [ ] Add unique/index constraints.

## EPIC B — Authorization/access

- [ ] Implement `Project::accessibleBy()`.
- [ ] Implement task membership-aware access.
- [ ] Replace route binding `ownedBy()` assumptions.
- [ ] Redesign `ProjectPolicy`.
- [ ] Redesign `TaskPolicy`.
- [ ] Add dedicated `changeStatus` permission.
- [ ] Add dedicated `assign` permission.
- [ ] Protect nested project/task routes.

## EPIC C — Member management

- [ ] Project Team/Members page.
- [ ] Invite Member flow.
- [ ] Accept invitation flow.
- [ ] Revoke invitation.
- [ ] Resend invitation.
- [ ] Remove Member.
- [ ] Owner protection.
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

## EPIC G — Tests

- [ ] Membership tests.
- [ ] Invitation tests.
- [ ] Role/policy tests.
- [ ] Assignment tests.
- [ ] Member status tests.
- [ ] Cross-project access tests.
- [ ] My Work tests.
- [ ] Activity actor tests.

---

# 68. Sprint Backlog — P1 Should Have

- [ ] Database + email invitation notifications.
- [ ] Task assignment database notification.
- [ ] Notification bell/unread count.
- [ ] Notification center/list.
- [ ] Mark read / mark all read.
- [ ] Assignee filters on project task list.
- [ ] Assignee filters on board.
- [ ] Project-scoped labels migration.
- [ ] Admin export authorization.
- [ ] Collaboration-aware dashboard counts.
- [ ] Invitation pending state UI.

---

# 69. Sprint Backlog — P2 Stretch

- [ ] Ownership transfer.
- [ ] Team Work screen.
- [ ] Role-change activity feed.
- [ ] Member-removal notification.
- [ ] Project membership activity feed entries.
- [ ] Team analytics first version.
- [ ] queued email notifications after commit.

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
```

## Phase 2 — Migration/backfill

Make all existing projects/tasks/users compatible with the new schema.

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

Database first; email where needed.

## Phase 8 — Full regression/security test pass

Do not finish the sprint with UI complete but authorization partially migrated.

---

# 71. Definition of Done

The sprint is considered complete only if all of the following are true.

## Membership

- [ ] Every project has exactly one Owner.
- [ ] Existing project owners were migrated without losing access.
- [ ] A user may be Member/Admin/Owner depending on the project.
- [ ] A non-member cannot access project data by URL/query.

## Invitations

- [ ] Owner/Admin can invite according to policy.
- [ ] Invitation has secure expiring token.
- [ ] Invite can be accepted exactly once.
- [ ] Duplicate/existing-member invitations are prevented.
- [ ] Invitation acceptance creates membership atomically.

## Roles

- [ ] OWNER/ADMIN/MEMBER behavior matches permission matrix.
- [ ] Member cannot mutate task content/priority/due date/assignment.
- [ ] Member can change status only on their assigned task.
- [ ] Role checks are enforced server-side.

## Assignment

- [ ] Task has zero or one assignee.
- [ ] Assignee must belong to project.
- [ ] Owner/Admin can assign/reassign/unassign.
- [ ] Member removal safely unassigns work.
- [ ] Assignment is visible on board/list/detail.

## My Work

- [ ] My Work means assigned-to-me across accessible projects.
- [ ] Existing personal tasks remain visible after migration.

## Activity

- [ ] Activity records actor separately from creator/assignee.
- [ ] Assignment/status changes show correct actor.
- [ ] Historical activity remains append-only.

## Security

- [ ] Cross-project crafted URLs are rejected.
- [ ] Search/activity/analytics/export do not leak inaccessible project data.
- [ ] Nested routes verify task belongs to project.

## Testing

- [ ] Existing relevant tests pass after refactor.
- [ ] New policy/authorization tests exist for all three roles.
- [ ] Invitation and assignment happy/error paths are covered.
- [ ] Frontend production build passes.

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
And Sara receives an invitation
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
└── Assignment Events

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
Notification foundation
```

## PlanOps 1.2 — Collaboration Experience

Next:

```text
Notification center polish
Team Work
Project-scoped labels
Team analytics
Ownership transfer
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
3. **One Owner per project.**
4. **One assignee per task.**
5. **Assignee must be an active project member.**
6. **Member can change only the status of their own assigned tasks.**
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
- queue mail later where appropriate.

## Risk 6 — Sprint overload

Collaboration touches most application queries.

Mitigation:

- treat P0 as the true sprint boundary;
- notification polish/team analytics remain P1/P2;
- do not begin Daily Planning in this sprint.

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

