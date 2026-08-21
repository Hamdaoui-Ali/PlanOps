# PlanOps
## Complete Product, Domain, UX, Analytics & Laravel Architecture Specification

**Document version:** 1.0  
**Status:** Product design baseline  
**Date:** 2026-08-20  
**Product name:** PlanOps  
**Primary implementation baseline:** Laravel 13 / PHP 8.3  
**Product type:** Personal project and work-tracking web application  

---

# 1. Executive Summary

PlanOps is a personal project and work-tracking platform inspired by the most useful parts of Jira and Linear, but deliberately reduced to the needs of one person managing multiple real projects.

The product exists to answer a simple set of questions clearly:

- What projects am I currently working on?
- What work exists inside each project?
- What have I not started yet?
- What am I working on now?
- What is waiting for review?
- What is blocked?
- What have I completed?
- What changed today?
- What progress did I make this week, month, or year?
- Which projects are moving and which have become stagnant?

PlanOps does **not** monitor the user's computer, infer activity, run a productivity timer, or automatically decide what the user worked on. The user explicitly records the work and changes its state. PlanOps records those explicit changes and converts them into useful views, history, and analytics.

The core product loop is:

```text
Create Project
     ↓
Create Tasks
     ↓
Break Tasks into Subtasks when needed
     ↓
Move work through explicit statuses
     ↓
PlanOps records the changes
     ↓
Dashboard and analytics summarize progress
```

The core domain is intentionally small:

```text
User
 ├── Projects
 │    └── Tasks
 │         ├── Subtasks
 │         ├── Labels
 │         └── Activity History
 │
 ├── My Work
 ├── Dashboard
 └── Analytics
```

This specification supersedes the earlier planning-heavy interpretation of the project. PlanOps is **not** primarily a daily planner, reminder system, time tracker, calendar, habit tracker, or capacity-planning application.

---

# 2. Product Definition

## 2.1 One-sentence definition

> **PlanOps is a personal Jira-style work operations dashboard where one user organizes projects into tasks and subtasks, manually moves work through a clear workflow, and uses the resulting history to understand progress across today, the week, the month, and the year.**

## 2.2 Product promise

PlanOps should become the single place where the user can open the application and immediately understand:

1. what work exists;
2. where every important task currently stands;
3. what has recently changed;
4. what has been completed;
5. how each project is progressing;
6. how work has evolved over time.

## 2.3 Product personality

PlanOps should feel:

- structured but not bureaucratic;
- powerful but not enterprise-heavy;
- dense enough for serious work but not visually overwhelming;
- fast to update;
- predictable;
- trustworthy;
- useful even with only a few projects and tens of tasks;
- equally useful for software projects, automation projects, study work, job-search work, or personal initiatives.

---

# 3. The Problem PlanOps Solves

A normal to-do list stores isolated items:

```text
- Fix migration issue
- Improve automation
- Work on portfolio
```

That is not enough when the user works on several serious projects at the same time.

The user needs context:

```text
Project: Angular Migration Factory
Task: Fix Angular 20 → 21 feasibility failure
Status: In Progress
Priority: High
Subtasks: 2 / 4 Done
Last changed: Today 17:31
```

The user also needs a larger view:

```text
This week
- 14 tasks started
- 18 tasks completed
- 7 tasks moved to review
- 3 tasks currently blocked
- Angular Migration contributed 61% of completed work
```

Existing enterprise issue trackers can provide this, but they also introduce concepts that are unnecessary for a personal system: teams, permission schemes, organizational administration, complex workflow editors, sprint administration, marketplace plugins, service desks, and extensive configuration.

PlanOps keeps the high-value mental model while removing the organizational overhead.

---

# 4. Correct Mental Model

## 4.1 Responsibility truth

A **Task** represents work the user wants to track.

## 4.2 Project truth

A **Project** groups related work toward a larger outcome.

## 4.3 Current-state truth

The task's **Status** says where that work currently stands.

## 4.4 Historical truth

**TaskActivity** records how the work changed over time.

## 4.5 Analytics truth

Dashboards and reports are calculated from Projects, Tasks, and TaskActivity.

No manually synchronized statistics table is the source of truth.

---

# 5. Core Product Principles

## 5.1 The user declares reality

PlanOps does not infer work state.

The user explicitly says:

- I started this task.
- This task is now in review.
- This task is blocked.
- This task is done.
- This task is no longer required.

PlanOps records that declaration.

## 5.2 History is automatic; work state is not

When the user moves a task from `NOT_STARTED` to `IN_PROGRESS`, PlanOps automatically records an activity event.

This is different from automatically tracking the user's behavior.

## 5.3 A status is not a productivity judgment

`IN_PROGRESS` describes state. It does not mean the user was continuously productive.

`DONE` describes completion. It does not measure effort.

`BLOCKED` describes inability to proceed. It is not a negative productivity score.

## 5.4 Metrics must be provable from stored facts

PlanOps may accurately say:

> Task AMF-42 entered In Progress at 10:13 and Done at 16:42.

PlanOps must not claim:

> You worked 6 hours and 29 minutes on AMF-42.

The second statement would require actual time tracking, which is outside the core product.

## 5.5 Projects and tasks are different lifecycle levels

A project may remain `ACTIVE` even when every current task is complete.

Project status is manual.

Task progress is derived.

## 5.6 Keep the workflow understandable

The first version has a fixed, opinionated workflow rather than a workflow builder.

## 5.7 Accessibility is part of the interaction model

The application must never require drag-and-drop, color perception, or highly dense layouts to perform essential actions.

## 5.8 YAGNI applies aggressively

The product should not gain a subsystem merely because Jira or another platform has it.

A feature belongs in PlanOps only when it improves personal project/work tracking.

---

# 6. What PlanOps Is Not

PlanOps is not intended to be:

- a Jira clone;
- a team collaboration suite;
- a Scrum management system;
- a sprint planning tool;
- a Gantt application;
- a full calendar;
- a time tracker;
- an employee-monitoring system;
- a habit tracker;
- a personal finance tracker;
- a CRM;
- a document management system;
- a chat platform;
- a notification-heavy reminder product;
- a full OKR platform;
- a service desk;
- a source-code host;
- a replacement for GitHub or GitLab.

These boundaries are intentional.

---

# 7. Target User

The initial target user is one technically comfortable person who works on several projects simultaneously and wants a structured record of work.

Typical projects may include:

- software development;
- migration projects;
- automation workflows;
- AI experiments;
- job-search activities;
- certifications;
- learning projects;
- portfolio projects;
- freelance work;
- personal initiatives.

PlanOps is designed as a personal system even though the database remains user-scoped so the architecture does not prevent future multi-account support.

---

# 8. Primary User Stories

## 8.1 Project organization

As a user, I want to create a project so that related work has a clear home.

As a user, I want to see every active project and its progress so that I know where my attention is going.

## 8.2 Task capture

As a user, I want to quickly create a task inside a project so that I can record work without breaking focus.

## 8.3 Workflow management

As a user, I want to move tasks between statuses so that PlanOps reflects the current state of my work.

## 8.4 Work decomposition

As a user, I want to break a larger task into subtasks so that I can track smaller concrete steps.

## 8.5 Cross-project work view

As a user, I want one My Work screen showing tasks from all projects so that I do not have to open each project individually.

## 8.6 Daily visibility

As a user, I want to see what is currently in progress, what I completed today, and what changed today.

## 8.7 Historical visibility

As a user, I want to see weekly, monthly, and yearly trends so that I can understand how my projects have progressed.

## 8.8 Traceability

As a user, I want every important task state change recorded automatically so that I can later reconstruct what happened.

---

# 9. Product Information Architecture

The recommended global navigation is:

```text
PLANOPS

OVERVIEW
  Dashboard

WORK
  My Work
  Projects

INSIGHTS
  Analytics
  Activity

SYSTEM
  Settings
```

A project has its own local navigation:

```text
PROJECT NAME
  Overview
  Board
  Tasks
  Activity
  Analytics
  Settings
```

The global search control remains available from all major screens.

---

# 10. Core Domain Entities

PlanOps has six fundamental persistent concepts:

1. User
2. Project
3. Task
4. Label
5. TaskLabel
6. TaskActivity

A small UserPreference entity stores personal display and timezone choices.

No daily-plan, timer, recurring-task, reminder, capacity, sprint, or team model is required for the core system.

---

# 11. User

The user owns all PlanOps data.

Every user-owned query must be scoped by `user_id`, even in a single-user deployment.

This protects the domain model and keeps future multi-account deployment possible without redesigning ownership.

---

# 12. User Preferences

Recommended fields:

```text
id
user_id
 timezone
week_start_day
theme
density
created_at
updated_at
```

## 12.1 Timezone

Use an IANA timezone such as:

```text
Africa/Casablanca
Europe/Paris
America/New_York
```

Never store a fixed UTC offset as the user's timezone.

## 12.2 Week start

Supported values:

```text
MONDAY
SUNDAY
```

Monday is the recommended default.

## 12.3 Theme

```text
SYSTEM
LIGHT
DARK
```

## 12.4 Density

```text
COMFORTABLE
COMPACT
```

Comfortable is the default.

---

# 13. Project

A Project represents a meaningful area of work that contains tasks.

Examples:

```text
Angular Migration Factory
Twitter Automation
PlanOps
Portfolio Website
Job Search
AZ-900 Preparation
```

## 13.1 Recommended Project fields

```text
id
user_id
name
key
description
status
color
icon
start_on
target_on
archived_at
created_at
updated_at
```

## 13.2 Project name

Required.

Example:

```text
Angular Migration Factory
```

## 13.3 Project key

A short uppercase identifier used to build readable task IDs.

Examples:

```text
AMF
PLAN
AUTO
PORT
JOB
```

Rules:

- 2 to 10 characters;
- uppercase letters and numbers;
- unique per user;
- no spaces;
- once the project contains tasks, changing the key is not supported in the normal UI.

## 13.4 Project task numbering

Each project maintains a monotonically increasing task number.

Example:

```text
AMF-1
AMF-2
AMF-3
...
AMF-42
```

Numbers are never reused after deletion.

A missing number is acceptable.

Task identifiers are identity labels, not row counts.

---

# 14. Project Status

Project lifecycle status is manual.

Supported states:

```text
PLANNED
ACTIVE
ON_HOLD
COMPLETED
CANCELLED
```

Archive is not a lifecycle status. It is represented separately by `archived_at`.

## 14.1 PLANNED

The project is known but work has not meaningfully started.

## 14.2 ACTIVE

The project is currently being worked on.

## 14.3 ON_HOLD

The project remains relevant but active work is intentionally paused.

## 14.4 COMPLETED

The user considers the project finished.

## 14.5 CANCELLED

The project is intentionally abandoned or no longer needed.

## 14.6 Important rule

PlanOps never automatically changes project status when tasks are completed.

The application may display a suggestion such as:

> All open tasks are complete. Consider marking this project Completed.

It does not perform the change itself.

---

# 15. Project Archive

Archived projects are hidden from normal active views but remain available historically.

Archiving is appropriate when:

- a project is completed and no longer needs regular visibility;
- a cancelled project should remain available for history;
- an old project should not clutter navigation.

Archiving does not delete tasks or activity.

---

# 16. Task

A Task is the fundamental unit of work in PlanOps.

Every task belongs to exactly one Project.

A task may optionally belong to another task as a one-level subtask.

## 16.1 Recommended fields

```text
id
user_id
project_id
parent_task_id
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
created_at
updated_at
deleted_at
```

## 16.2 Display identifier

The displayed task key is derived from the project key and task number.

```text
{PROJECT_KEY}-{TASK_NUMBER}
```

Example:

```text
AMF-42
```

The value does not need a separate database column if it can be derived reliably.

---

# 17. Task Title

Required.

The title should describe an observable piece of work.

Good:

```text
Fix Angular 20 → 21 feasibility failure
Add project progress chart
Validate writer structured output
Update portfolio project cards
```

Weak:

```text
Angular
Stuff
Work
Fix things
```

The UI should not enforce writing quality, but should make good titles easy.

---

# 18. Task Description

Optional.

Description supports structured text, ideally Markdown or a restrained rich-text subset.

It can contain:

- context;
- acceptance notes;
- technical details;
- links;
- commands;
- checklists represented as prose when subtasks are unnecessary.

The description is not a document-management system.

---

# 19. Task and Subtask Hierarchy

PlanOps intentionally supports only:

```text
Project
 └── Task
      └── Subtask
```

A subtask cannot contain another subtask.

This gives enough decomposition for personal work without creating an arbitrary tree.

## 19.1 Structural rules

- a top-level task has `parent_task_id = null`;
- a subtask has `parent_task_id = <task id>`;
- parent and child must belong to the same project;
- a subtask cannot be its own parent;
- a subtask cannot become the parent of another task;
- moving a top-level task to another project requires all its subtasks to move with it in the same transaction.

---

# 20. Why PlanOps Does Not Add Epic/Story/Bug Yet

Jira supports richer work-type hierarchies because it serves many teams and processes.

PlanOps does not need that complexity initially.

For the personal system:

```text
Project → Task → Subtask
```

is enough.

If the user wants to distinguish a bug, research item, documentation task, or feature, labels provide that classification without introducing another configuration subsystem.

---

# 21. Task Status Workflow

The default PlanOps workflow is:

```text
BACKLOG
   ↓
NOT_STARTED
   ↓
IN_PROGRESS
   ↓
IN_REVIEW
   ↓
DONE
```

Two additional states exist:

```text
BLOCKED
CANCELLED
```

The complete status set is:

```text
BACKLOG
NOT_STARTED
IN_PROGRESS
IN_REVIEW
BLOCKED
DONE
CANCELLED
```

---

# 22. Status Semantics

## 22.1 BACKLOG

The work is known but not yet committed to the near-term active workload.

Examples:

- an improvement idea;
- a future technical task;
- work that may be useful later.

## 22.2 NOT_STARTED

The task is accepted and expected to be worked on, but work has not started yet.

## 22.3 IN_PROGRESS

The user considers the task actively under execution.

This does not mean continuous work is occurring every minute.

## 22.4 IN_REVIEW

The primary work is complete from the user's perspective and is being reviewed, tested, checked, validated, or awaiting a decision.

This status is useful even in a personal system because review may involve:

- a manager;
- a teammate outside PlanOps;
- the user's own verification step;
- a test run;
- external feedback;
- a code review.

## 22.5 BLOCKED

The user cannot currently proceed.

Examples:

- waiting for credentials;
- waiting for an environment;
- waiting for another person;
- dependent technical issue;
- external service unavailable.

The UI may offer an optional blocked-reason note, but the reason is not required to change status.

## 22.6 DONE

The task is completed.

## 22.7 CANCELLED

The task will not be completed because it is no longer required or relevant.

Cancelled work is different from Done work and is excluded from progress denominators.

---

# 23. Status Categories

For analytics, statuses map to three stable categories:

```text
PLANNED
  BACKLOG
  NOT_STARTED

ACTIVE
  IN_PROGRESS
  IN_REVIEW
  BLOCKED

TERMINAL
  DONE
  CANCELLED
```

These categories simplify reporting without hiding the more useful detailed statuses.

---

# 24. Workflow Transition Policy

PlanOps is a personal system, so the workflow should not behave like a rigid enterprise approval engine.

The user may move a non-terminal task directly to another state when reality requires it.

Examples that are valid:

```text
BACKLOG → IN_PROGRESS
NOT_STARTED → IN_REVIEW
BLOCKED → DONE
IN_PROGRESS → NOT_STARTED
```

The UI may present the normal path first, but the database does not need a large transition matrix.

## 24.1 Reopening

Any transition from `DONE` or `CANCELLED` to a non-terminal status is considered a reopen.

Example:

```text
DONE → IN_PROGRESS
```

PlanOps records a `STATUS_CHANGED` event whose metadata identifies it as a reopen.

## 24.2 Completion timestamp

When status becomes `DONE`:

```text
completed_at = current timestamp
cancelled_at = null
```

When a Done task is reopened:

```text
completed_at = null
```

Historical completion remains available in TaskActivity.

## 24.3 Cancellation timestamp

When status becomes `CANCELLED`:

```text
cancelled_at = current timestamp
completed_at = null
```

When reopened:

```text
cancelled_at = null
```

## 24.4 First started timestamp

`first_started_at` is set the first time a task enters `IN_PROGRESS`.

It is never overwritten by later resumes.

---

# 25. Priority

Supported values:

```text
LOW
MEDIUM
HIGH
URGENT
```

Default:

```text
MEDIUM
```

Priority describes importance, not workflow state.

Examples:

```text
URGENT + BLOCKED
HIGH + NOT_STARTED
LOW + IN_REVIEW
```

---

# 26. Due Date

A task may have an optional date-only deadline:

```text
due_on DATE
```

PlanOps does not invent a fake 23:59 timestamp.

A task is overdue when:

```text
user_local_date > due_on
AND status NOT IN (DONE, CANCELLED)
```

`OVERDUE` is a computed condition, not a task status.

---

# 27. Labels

Labels provide flexible classification without adding work-type configuration.

Examples:

```text
frontend
backend
bug
devops
ai
migration
documentation
testing
research
job-search
```

Recommended Label fields:

```text
id
user_id
name
normalized_name
color
created_at
updated_at
```

Rules:

- label names are unique per user after normalization;
- a task may have many labels;
- a label may belong to many tasks;
- deleting a label detaches it from tasks but does not delete tasks.

---

# 28. Task Position and Ordering

Tasks require explicit position within board/list groupings.

Recommended initial implementation:

```text
position INTEGER
```

Position is interpreted within a relevant grouping such as:

```text
project + status
```

Reordering is performed transactionally.

For a small personal dataset, integer renumbering is acceptable and simpler than fractional-ranking systems.

---

# 29. Task Deletion

Normal deletion uses soft deletion.

Reasons:

- accidental deletion can be recovered;
- activity history remains meaningful;
- task identifiers are never reused;
- project history is not silently rewritten.

Soft-deleted tasks are excluded from active views and normal progress calculations.

A future account-purge operation may permanently remove user-owned data.

---

# 30. Task Activity: The Historical Core

TaskActivity is one of the most important entities in PlanOps.

It records meaningful changes made by the user.

Example timeline:

```text
20 Aug 2026 09:12
AMF-42 created

20 Aug 2026 10:22
Status: Not Started → In Progress

20 Aug 2026 14:07
Priority: Medium → High

20 Aug 2026 17:31
Status: In Progress → In Review

21 Aug 2026 09:15
Status: In Review → Done
```

The user does not manually create this audit trail.

PlanOps creates it as a consequence of explicit domain actions.

---

# 31. TaskActivity Schema

Recommended fields:

```text
id
user_id
project_id
task_id
event_type
field
old_value
new_value
metadata
created_at
```

Recommended database types:

```text
event_type VARCHAR / backed enum
field nullable VARCHAR
old_value JSONB nullable
new_value JSONB nullable
metadata JSONB nullable
```

JSON values allow history to preserve structured state without creating a column for every possible event payload.

---

# 32. Activity Event Types

Core events:

```text
TASK_CREATED
TASK_UPDATED
STATUS_CHANGED
PRIORITY_CHANGED
DUE_DATE_CHANGED
LABEL_ADDED
LABEL_REMOVED
SUBTASK_CREATED
TASK_MOVED_PROJECT
TASK_DELETED
TASK_RESTORED
```

Project lifecycle events may use a separate project activity later, but the first version can derive project history from project timestamps plus task activity.

---

# 33. Activity Recording Rules

Activity history should record meaningful domain changes, not every internal database mutation.

Record:

- creation;
- status changes;
- priority changes;
- due-date changes;
- labels added/removed;
- movement between projects;
- subtask creation;
- deletion/restoration.

Do not create noisy activity events for:

- a background cache refresh;
- a chart query;
- opening a page;
- changing a client-side filter;
- re-rendering a component.

A description/title edit may use one generic `TASK_UPDATED` event rather than recording full text before and after, reducing sensitive log duplication.

---

# 34. Global Activity Feed

The global Activity page answers:

> What changed across all my projects?

Example:

```text
Today
────────────────────────────────────────────
18:30  AMF-42  In Progress → In Review
17:42  PLAN-14 Subtask completed
16:11  AUTO-31 Priority Medium → High
14:06  PLAN-19 Created
11:20  AMF-38 In Review → Done
```

Filters:

```text
Project
Event type
Date range
Task
```

---

# 35. Task Activity Timeline

Every task has its own timeline.

Example:

```text
ACTIVITY

Today
18:30  Status changed
       In Progress → In Review

17:42  Subtask updated
       Add regression test → Done

15:17  Priority changed
       Medium → High

11:06  Status changed
       Not Started → In Progress

Yesterday
16:22  Task created
```

The timeline is chronological, readable, and not presented as raw JSON.

---

# 36. Project Progress

Project progress is a derived value.

Do not store an authoritative `progress_percent` column.

## 36.1 Formula

Only top-level tasks count.

```text
eligible_tasks = top-level tasks where status != CANCELLED
completed_tasks = eligible top-level tasks where status = DONE

project_progress = completed_tasks / eligible_tasks × 100
```

If `eligible_tasks = 0`, progress is displayed as `0%` with the label `No active scope` rather than pretending work is complete.

## 36.2 Why subtasks do not directly affect project percentage

Suppose:

```text
Task A has 2 subtasks
Task B has 20 subtasks
```

Counting every subtask would make Task B ten times more important in project progress.

Instead:

- project progress uses top-level tasks;
- each task independently displays subtask completion.

---

# 37. Subtask Progress

A top-level task with subtasks displays:

```text
completed non-cancelled subtasks
─────────────────────────────── × 100
all non-cancelled subtasks
```

Example:

```text
6 / 8 subtasks done
75%
```

Subtask progress is informative only.

It does not automatically change the parent's status.

If all subtasks are Done, PlanOps may suggest completing the parent but does not do so automatically.

---

# 38. Dashboard Purpose

The Dashboard is not a decorative page.

Its purpose is to answer:

> What is happening with my work right now and how has it changed over the selected period?

The dashboard combines:

- current-state metrics;
- period-based activity metrics;
- trends;
- project contribution;
- attention indicators.

---

# 39. Global Dashboard Period Selector

The primary dashboard selector is:

```text
[ Today ] [ Week ] [ Month ] [ Year ] [ Custom ]
```

Each period changes the analytics interval while preserving a stable page structure.

Date boundaries use the user's configured timezone.

---

# 40. Dashboard Metric Classes

PlanOps distinguishes three metric classes.

## 40.1 Current-state metrics

Examples:

```text
Active projects now
Tasks In Progress now
Tasks In Review now
Tasks Blocked now
Overdue tasks now
```

These do not depend on the selected historical period.

## 40.2 Period-event metrics

Examples:

```text
Tasks created during period
Tasks started during period
Tasks completed during period
Tasks moved to review during period
Tasks blocked during period
Tasks reopened during period
```

These are derived from creation timestamps and activity events.

## 40.3 Trend metrics

Examples:

```text
Completed per day/week/month
Created vs completed
Progress by project
Status-duration trends
```

---

# 41. Global KPI Cards

Recommended top row:

```text
Active Projects
In Progress
In Review
Blocked
Completed in Period
```

Optional secondary KPI:

```text
Overdue
```

Example:

```text
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ 4             │  │ 12            │  │ 5             │
│ Active        │  │ In Progress   │  │ In Review     │
│ Projects      │  │               │  │               │
└───────────────┘  └───────────────┘  └───────────────┘

┌───────────────┐  ┌───────────────┐
│ 3             │  │ 18            │
│ Blocked       │  │ Completed     │
│               │  │ This Week     │
└───────────────┘  └───────────────┘
```

---

# 42. Today's Dashboard

Today emphasizes immediate work visibility.

Recommended sections:

## 42.1 Currently Working On

Tasks currently `IN_PROGRESS`.

```text
AMF-42   Fix Angular G05                 High
PLAN-7   Design task workflow            Medium
```

## 42.2 In Review

Tasks currently `IN_REVIEW`.

```text
AUTO-31  Validate critic output          High
```

## 42.3 Blocked

Tasks currently `BLOCKED`.

## 42.4 Due Today / Overdue

Tasks requiring attention based on `due_on`.

## 42.5 Today's Activity

Examples:

```text
4 tasks completed
3 tasks started
2 tasks moved to review
1 task blocked
1 task reopened
```

## 42.6 Recent Activity Timeline

Chronological event feed for today.

---

# 43. Weekly Dashboard

The week view focuses on throughput and movement.

Recommended components:

- completed tasks per day;
- created vs completed;
- project contribution;
- current status distribution;
- tasks started;
- tasks moved to review;
- tasks blocked;
- reopened tasks;
- weekly activity feed.

Example:

```text
Tasks completed
Mon ██████      6
Tue ████        4
Wed ███████     7
Thu █████       5
Fri ███         3
```

---

# 44. Monthly Dashboard

The month view focuses on broader project movement.

Recommended components:

- completion trend by week;
- created vs completed by week;
- project contribution;
- current status distribution;
- projects with most completions;
- blocked/reopened counts;
- oldest open tasks;
- monthly activity heatmap.

---

# 45. Yearly Dashboard

The year view provides a long-term record of progress.

Recommended components:

- completed tasks by month;
- created tasks by month;
- cumulative completed work;
- completion by project;
- activity heatmap;
- project timeline summary;
- total projects started/completed;
- longest-running active projects.

Example:

```text
Completed tasks
Jan ███████
Feb █████████
Mar ██████
Apr █████████████
May ███████████████
Jun ████████████
Jul ██████████████████
Aug █████████████
```

---

# 46. Custom Date Range

Custom range accepts:

```text
from_date
to_date
```

The same period metrics are recalculated for that range.

The UI must show the chosen range clearly so the user never mistakes a custom report for the current month or year.

---

# 47. Analytics Metric Definitions

Metrics must have exact semantics.

## 47.1 Created Tasks

```text
count(top-level tasks created during period)
```

Soft-deleted tasks are excluded from standard reports.

## 47.2 Completed Tasks

```text
count(distinct top-level task_id
      where STATUS_CHANGED new status = DONE
      during period)
```

If a task is completed, reopened, and completed again within the same period, the standard `Completed Tasks` KPI counts that task once.

The Activity view still exposes every transition.

## 47.3 Started Tasks

```text
count(distinct top-level task_id
      where STATUS_CHANGED new status = IN_PROGRESS
      during period)
```

## 47.4 Moved to Review

```text
count(distinct top-level task_id
      where STATUS_CHANGED new status = IN_REVIEW
      during period)
```

## 47.5 Became Blocked

```text
count(distinct top-level task_id
      where STATUS_CHANGED new status = BLOCKED
      during period)
```

## 47.6 Reopened

A reopen is any transition:

```text
old status IN (DONE, CANCELLED)
AND new status NOT IN (DONE, CANCELLED)
```

The standard KPI counts distinct top-level tasks reopened in the period.

---

# 48. Created vs Completed

One of the most useful PlanOps charts compares scope entering the system against work being completed.

For interval bucket `B`:

```text
created(B)   = top-level tasks created in B
completed(B) = distinct top-level tasks entering DONE in B
balance(B)   = created(B) - completed(B)
```

Interpretation:

```text
balance > 0  → tracked scope grew
balance = 0  → created and completed were balanced
balance < 0  → more tracked work was completed than created
```

PlanOps must not call this a productivity score.

---

# 49. Current Status Distribution

This is a snapshot, not a historical count.

Recommended visualization:

```text
Backlog       31
Not Started   22
In Progress   14
In Review      5
Blocked        4
Done          33
```

Cancelled tasks are hidden by default in the main distribution but may be enabled.

Only top-level tasks are included in the main global distribution.

---

# 50. Project Contribution

For a selected period:

```text
project_completed(P)
──────────────────── × 100
all_completed(P)
```

where counts use distinct top-level tasks entering Done in the period.

Example:

```text
Angular Migration      52%
Twitter Automation     24%
PlanOps                17%
Other                   7%
```

The chart describes where completed tracked work occurred. It does not measure hours or difficulty.

---

# 51. Work Activity Heatmap

PlanOps may provide a GitHub-style heatmap.

The heatmap should use a clearly defined activity score, not every database update.

Recommended activity events:

- top-level task creation;
- subtask creation;
- status changes.

Do not count:

- opening a page;
- filter changes;
- description autosave keystrokes;
- background requests;
- dashboard refreshes.

The heatmap is labeled **Tracked Work Activity**, not Productivity.

---

# 52. Lead Time

Lead time is elapsed calendar time from task creation to completion.

For a completed task:

```text
DONE transition timestamp - created_at
```

If the task was completed multiple times, period analytics use the relevant completion event and clearly label the interpretation.

Recommended initial project-level statistic:

```text
Median lead time for tasks completed in selected period
```

Median is preferred for a personal dataset because a few very old tasks can heavily distort the average.

---

# 53. Cycle Time

Cycle time is elapsed calendar time from first explicit start to completion:

```text
DONE transition timestamp - first_started_at
```

Only tasks with `first_started_at` are included.

Important wording:

> Cycle time is elapsed time between states; it is not active work duration.

---

# 54. Time in Status

TaskActivity allows PlanOps to derive elapsed time spent in statuses.

Example:

```text
Not Started   2d 4h
In Progress   1d 8h
In Review     6h
Blocked       3d 2h
```

For aggregate analytics, PlanOps can show median time in status across selected completed tasks.

Again, this represents elapsed workflow time, not active effort.

---

# 55. Project Progress History

A project analytics page may reconstruct progress over time from task creation, cancellation, and completion history.

At historical instant `T`:

```text
scope(T) = top-level tasks that existed at T and were not cancelled at T
finished(T) = tasks in scope(T) whose state at T was DONE
progress(T) = finished(T) / scope(T)
```

Adding new tasks can cause project progress percentage to decrease.

That is correct: scope increased.

PlanOps should not smooth or hide that change.

---

# 56. Project Overview Screen

The Project Overview answers:

> What is the current health and state of this project?

Recommended layout:

```text
Project Header
  Name
  Key
  Status
  Target date
  Progress

Current Work
  In Progress
  In Review
  Blocked

Progress Summary
  Done / total
  Not Started
  Current status distribution

Recent Activity

Upcoming / Overdue
```

---

# 57. Projects Index

The Projects screen shows cards or rows such as:

```text
┌──────────────────────────────────┐
│ Angular Migration Factory        │
│ ACTIVE                           │
│ ███████████████░░ 74%            │
│ 23 / 31 tasks done               │
│ 4 in progress · 2 review · 1 blocked
└──────────────────────────────────┘
```

Filters:

```text
Status
Archived / Active
Target date
```

Sort:

```text
Recently updated
Name
Progress
Target date
Created date
```

---

# 58. Project Board

The Board is the tactical project screen.

Default columns:

```text
BACKLOG | NOT STARTED | IN PROGRESS | IN REVIEW | BLOCKED | DONE
```

Cancelled tasks are hidden by default and available through filters.

Each card represents one top-level task.

Recommended card fields:

```text
Task key
Title
Priority
Due date if present
Label chips
Subtask progress
```

Cards should remain visually concise.

---

# 59. Board Interaction

## 59.1 Drag and drop

Dragging a card between columns may change status.

## 59.2 Non-drag equivalent

Every card/task must also expose a status selector or `Move to…` command.

No essential operation may require dragging.

## 59.3 Reordering

Dragging within a column changes `position` only.

## 59.4 Confirmation

Normal status changes do not require confirmation.

Destructive actions such as Delete require confirmation.

Moving to Cancelled may use a lightweight confirmation if the interaction design proves it prevents accidental cancellation without adding friction.

---

# 60. Project Task List

Some work is easier to scan in a table/list than on a board.

Recommended columns:

```text
Key
Title
Status
Priority
Due
Labels
Subtasks
Updated
```

The user can:

- filter;
- sort;
- open task details;
- create a task;
- change status quickly;
- change priority quickly.

---

# 61. My Work

My Work is a cross-project task view.

It is one of the most important screens because the user frequently cares about current work more than project boundaries.

Default view emphasizes:

```text
IN_PROGRESS
IN_REVIEW
BLOCKED
NOT_STARTED
```

Backlog and Done remain accessible through filters.

Example:

```text
AMF-42   Angular Migration   Fix G05 failure       IN PROGRESS
PLAN-12  PlanOps             Design dashboard      IN PROGRESS
AUTO-31  Twitter Automation  Critic validation     IN REVIEW
PORT-4   Portfolio           Update project cards  NOT STARTED
```

---

# 62. My Work Filters

Core filters:

```text
Project
Status
Priority
Label
Due state
Created date
Updated date
```

Due-state shortcuts:

```text
Overdue
Due today
Due this week
No due date
```

---

# 63. My Work Sorting

Core sort options:

```text
Recently updated
Recently created
Priority
Due date
Task key
Project
```

Default:

```text
Recently updated
```

---

# 64. Task Detail Experience

Opening a task should not remove the user from context unnecessarily.

A desktop implementation may use a side panel / large drawer from board and list screens, with an option to open the task on a dedicated URL.

A task page should contain:

```text
Key + Title
Status
Priority
Project
Due date
Labels
Description
Subtasks
Activity
```

---

# 65. Task Detail Header

Recommended hierarchy:

```text
AMF-42
Fix Angular 20 → 21 feasibility failure

[In Progress ▼] [High ▼] [Due Aug 24] [...]
```

The task identifier is visually secondary to the title but easy to copy.

---

# 66. Subtask Experience

Subtasks appear as a compact list inside the parent task.

Example:

```text
Subtasks                         2 / 4
─────────────────────────────────────
✓ Reproduce G05 failure
✓ Capture CLI evidence
● Inspect feasibility rules
○ Add regression test
```

Each subtask can be opened as a task detail view with its own status, priority, due date, and activity if needed.

The parent shows aggregated subtask progress.

---

# 67. Quick Task Creation

Task creation must be fast.

Minimum required fields:

```text
Project
Title
```

Defaults:

```text
Status = NOT_STARTED
Priority = MEDIUM
```

Optional fields can be added immediately or later:

```text
Description
Status
Priority
Due date
Labels
Parent task
```

The creation experience should not force the user through a long form.

---

# 68. Search

Search is global and should support:

- task key;
- task title;
- task description;
- project name;
- labels.

Initial implementation can use PostgreSQL search/`ILIKE` without external search infrastructure.

Example query:

```text
G05
```

Possible results:

```text
AMF-42  Fix Angular G05 failure
AMF-37  Analyse G05 evidence
AMF-24  Add G05 UI state
```

---

# 69. Search Result Requirements

Each task result displays:

```text
Task key
Title
Project
Status
```

Project results display:

```text
Project name
Project key
Status
Progress
```

Search results must be keyboard navigable.

---

# 70. Global Analytics Screen

The Analytics screen provides deeper reporting than the Dashboard.

Recommended sections:

```text
Overview
Throughput
Workflow
Projects
Activity
```

It uses the same date-range control as Dashboard.

---

# 71. Analytics Overview

Recommended metrics:

```text
Created
Completed
Started
Moved to Review
Blocked
Reopened
Median Lead Time
Median Cycle Time
```

Only show time-based metrics when enough relevant data exists.

No-data state:

> Not enough completed tasks in this period to calculate cycle time.

Do not show misleading zeroes.

---

# 72. Analytics: Throughput

Charts:

- completed by day/week/month;
- created vs completed;
- cumulative completed tasks;
- completion by project.

---

# 73. Analytics: Workflow

Charts:

- current status distribution;
- median time in status;
- tasks entering review;
- tasks becoming blocked;
- reopened tasks.

---

# 74. Analytics: Projects

Charts/tables:

- current project progress;
- completed tasks by project;
- active tasks by project;
- blocked tasks by project;
- project progress history.

---

# 75. Analytics: Activity

Charts:

- tracked activity heatmap;
- status transitions over time;
- active days by month.

These views describe application-recorded work activity, not actual working hours.

---

# 76. Project Analytics Screen

Each project should have its own analytics context.

Recommended metrics:

```text
Current progress
Total top-level tasks
Done
In Progress
In Review
Blocked
Not Started
Completed in selected period
Created in selected period
Median lead time
Median cycle time
```

Charts:

```text
Progress over time
Created vs completed
Status distribution
Completed over time
```

---

# 77. Attention Indicators

PlanOps can help surface items that may need attention without changing them automatically.

Examples:

```text
Overdue tasks
Blocked tasks
Tasks in review for a long time
Active project with no recent task activity
Old Not Started tasks
```

These are suggestions/indicators, not automatic workflow actions.

---

# 78. Stale Task Definition

A future-facing but simple attention rule may define a stale active task as:

```text
status IN (IN_PROGRESS, IN_REVIEW, BLOCKED)
AND updated_at < now - configured threshold
```

For the baseline product, stale indicators may use a fixed 7-day threshold and be labeled clearly.

It is not necessary to expose a rule engine.

---

# 79. Dashboard Empty States

Empty states must teach the product instead of showing blank charts.

Examples:

No projects:

> Create your first project to start organizing work.

Project has no tasks:

> This project has no tracked work yet. Add the first task.

No period activity:

> No task state changes were recorded in this period.

No completed tasks:

> Nothing was marked Done in this period.

---

# 80. Visual Design Direction

PlanOps should feel like a modern engineering/productivity tool without copying another product's visual identity.

Recommended characteristics:

- dark mode as a first-class experience;
- restrained surface hierarchy;
- strong typography hierarchy;
- clear status chips with text;
- compact data tables with optional comfortable density;
- charts with accessible labels/tooltips;
- minimal decorative gradients;
- consistent card and panel geometry;
- subtle animation only where it clarifies state changes.

---

# 81. Accessibility and Cognitive Load

PlanOps targets WCAG 2.2 AA behavior for core interactions.

The application should also follow cognitive-accessibility principles consistent with the Dyslex.ai-oriented goal of reducing friction and visual overload.

## 81.1 No drag-only behavior

Any drag action must have an equivalent single-pointer/button/menu interaction.

Examples:

```text
Drag card to In Review
OR
Status → In Review
```

## 81.2 Pointer targets

Interactive targets should meet WCAG minimum sizing and should generally aim for a comfortable target larger than the absolute minimum.

## 81.3 Keyboard access

The user must be able to:

- navigate sidebar links;
- open tasks;
- use filters;
- change status;
- change priority;
- create tasks;
- submit forms;
- close dialogs;
- use search;

without a mouse.

## 81.4 Visible focus

Focus indicators must be clearly visible in light and dark themes.

## 81.5 Status is never color-only

Do not show only a colored dot.

Use:

```text
● In Review
```

not merely:

```text
●
```

## 81.6 Charts are not visual-only truth

Charts must have labels, accessible tooltips, and where practical a table/text summary.

## 81.7 Readable layout

Use:

- short blocks;
- clear headings;
- consistent locations for actions;
- left-aligned text;
- useful whitespace;
- predictable control labels.

Avoid:

- walls of text in dense cards;
- justified body text;
- tiny metadata everywhere;
- overly decorative typefaces;
- large all-caps paragraphs;
- rapidly moving UI.

## 81.8 No mandatory “dyslexia font”

PlanOps does not assume one special font is universally more readable.

Use a high-quality system/UI font stack and prioritize spacing, hierarchy, line length, density, and user choice.

## 81.9 Density control

`COMFORTABLE` and `COMPACT` modes let the user choose information density.

Comfortable remains the default.

## 81.10 Reduced motion

Respect `prefers-reduced-motion`.

Transitions must not be required to understand state.

---

# 82. Responsive Design

PlanOps is desktop-first because boards, tables, and analytics benefit from screen width.

It must nevertheless remain functional on mobile.

## 82.1 Desktop

- persistent sidebar;
- multi-column board;
- task drawer;
- full charts;
- dense list view.

## 82.2 Tablet

- collapsible sidebar;
- horizontally scrollable board;
- full-width task panel.

## 82.3 Mobile

- bottom/top navigation adaptation;
- list-first project view;
- board may horizontally scroll;
- status selector remains primary alternative to dragging;
- charts simplify without losing numeric summaries.

---

# 83. Database Model Overview

Recommended core tables:

```text
users
user_preferences
projects
tasks
labels
task_label
task_activities
```

The small schema is a deliberate strength.

---

# 84. `projects` Table

Conceptual schema:

```text
id BIGINT PK
user_id BIGINT FK users.id
name VARCHAR(160)
key VARCHAR(10)
description TEXT NULL
status VARCHAR(32)
color VARCHAR(32) NULL
icon VARCHAR(64) NULL
start_on DATE NULL
target_on DATE NULL
next_task_number BIGINT NOT NULL DEFAULT 1
archived_at TIMESTAMPTZ NULL
created_at TIMESTAMPTZ
updated_at TIMESTAMPTZ
```

Constraints:

```text
UNIQUE(user_id, key)
next_task_number >= 1
```

Recommended key validation:

```regex
^[A-Z0-9]{2,10}$
```

---

# 85. `tasks` Table

Conceptual schema:

```text
id BIGINT PK
user_id BIGINT FK users.id
project_id BIGINT FK projects.id
parent_task_id BIGINT NULL FK tasks.id
number BIGINT NOT NULL
title VARCHAR(300)
description TEXT NULL
status VARCHAR(32)
priority VARCHAR(32)
due_on DATE NULL
position INTEGER NOT NULL DEFAULT 0
first_started_at TIMESTAMPTZ NULL
completed_at TIMESTAMPTZ NULL
cancelled_at TIMESTAMPTZ NULL
status_changed_at TIMESTAMPTZ NOT NULL
created_at TIMESTAMPTZ
updated_at TIMESTAMPTZ
deleted_at TIMESTAMPTZ NULL
```

Constraints:

```text
UNIQUE(project_id, number)
position >= 0
parent_task_id != id
```

Application-level invariants enforce same-project parentage and one hierarchy level.

---

# 86. `labels` Table

```text
id BIGINT PK
user_id BIGINT FK users.id
name VARCHAR(80)
normalized_name VARCHAR(80)
color VARCHAR(32) NULL
created_at TIMESTAMPTZ
updated_at TIMESTAMPTZ
```

Constraint:

```text
UNIQUE(user_id, normalized_name)
```

---

# 87. `task_label` Table

```text
task_id BIGINT FK tasks.id
label_id BIGINT FK labels.id
created_at TIMESTAMPTZ
```

Primary/unique constraint:

```text
UNIQUE(task_id, label_id)
```

---

# 88. `task_activities` Table

```text
id BIGINT PK
user_id BIGINT FK users.id
project_id BIGINT FK projects.id
task_id BIGINT FK tasks.id
event_type VARCHAR(64)
field VARCHAR(64) NULL
old_value JSONB NULL
new_value JSONB NULL
metadata JSONB NULL
created_at TIMESTAMPTZ
```

TaskActivity is append-only through normal application flows.

It is not editable from the UI.

---

# 89. `user_preferences` Table

```text
id BIGINT PK
user_id BIGINT FK users.id
timezone VARCHAR(100)
week_start_day VARCHAR(16)
theme VARCHAR(16)
density VARCHAR(16)
created_at TIMESTAMPTZ
updated_at TIMESTAMPTZ
```

Constraint:

```text
UNIQUE(user_id)
```

---

# 90. Database Indexes

Recommended indexes:

## Projects

```text
(user_id, status)
(user_id, archived_at)
(user_id, updated_at DESC)
```

## Tasks

```text
(user_id, status)
(project_id, status, position)
(project_id, parent_task_id)
(user_id, priority)
(user_id, due_on)
(user_id, updated_at DESC)
(parent_task_id)
```

## Activities

```text
(user_id, created_at DESC)
(project_id, created_at DESC)
(task_id, created_at ASC)
(event_type, created_at DESC)
```

## Labels

```text
(user_id, normalized_name)
```

---

# 91. Data Ownership Rules

Every Project belongs to one user.

Every Task must belong to:

- the same user as its project;
- the same user as its parent task, when a parent exists.

Every TaskActivity must reference:

- the task owner;
- the task's project.

Every label assignment must involve a label and task owned by the same user.

These checks should exist in domain/application logic even when foreign keys protect basic relational integrity.

---

# 92. Task Number Allocation

Task numbers must remain unique under concurrent requests.

Recommended approach:

1. begin database transaction;
2. lock the project row for update;
3. read `next_task_number`;
4. create the task using that number;
5. increment `next_task_number`;
6. commit.

This is simple, reliable, and appropriate for PlanOps scale.

---

# 93. Transaction Boundaries

Use database transactions for multi-record domain operations.

Required examples:

## Create Task

```text
allocate project number
create task
record TASK_CREATED activity
```

## Change Task Status

```text
update status/timestamps
record STATUS_CHANGED activity
```

## Move Task to Another Project

```text
allocate new project task number if identifiers change by move policy
move parent + subtasks
record activity
```

The simplest v1 policy is to **not support moving tasks between projects after creation** through the normal UI. This prevents identifier-history ambiguity and removes unnecessary transactional complexity.

If a task was created in the wrong project, the user can recreate it or a later version can introduce a carefully defined move operation.

## Reorder Board

```text
update positions for affected status column
commit as one unit
```

---

# 94. Identifier Stability

Task keys should remain stable historical references.

Therefore:

- project key becomes effectively immutable after the first task exists;
- tasks do not move between projects in v1;
- task numbers are not reused.

This keeps links and activity references understandable.

---

# 95. Time Semantics

All real timestamps are stored as UTC-capable timestamps (`TIMESTAMPTZ` in PostgreSQL).

Date-only deadlines are stored as `DATE`.

Period calculations use the user's IANA timezone.

Example for Today in `Africa/Casablanca`:

1. determine local start and end of day;
2. convert boundaries to UTC;
3. query events using UTC boundaries.

This avoids timezone-dependent dashboard errors.

---

# 96. Application Architecture

PlanOps should use a Laravel modular monolith.

It does not need microservices.

Recommended logical modules:

```text
Identity
Projects
Tasks
Labels
Activity
Dashboard
Analytics
Search
Settings
```

All modules live in one Laravel application and one relational database.

---

# 97. Laravel Architectural Style

Recommended layers:

```text
HTTP Layer
  Controllers
  Form Requests
  Resources / Inertia responses

Application Layer
  Actions
  Query Services

Domain Layer
  Models
  Enums
  Rules
  Events
  Policies

Infrastructure
  Database
  Logging
  Cache only when proven necessary
```

Controllers remain thin.

Business mutations belong in explicit Actions.

Analytics/query composition belongs in Query Services.

---

# 98. Suggested Laravel Domain Structure

One possible structure:

```text
app/
  Domain/
    Projects/
      Actions/
      Enums/
      Models/
      Policies/
      Queries/

    Tasks/
      Actions/
      Enums/
      Events/
      Models/
      Policies/
      Queries/
      Rules/

    Labels/
      Actions/
      Models/

    Activity/
      Models/
      Queries/
      Services/

    Dashboard/
      Queries/

    Analytics/
      Queries/
      ValueObjects/

    Identity/
      Models/
      Policies/

  Http/
    Controllers/
    Requests/
    Resources/
```

Exact folders may adapt to the chosen Laravel conventions, but the domain boundaries should remain clear.

---

# 99. Domain Enums

Recommended PHP backed enums:

```text
ProjectStatus
TaskStatus
TaskPriority
TaskActivityType
ThemePreference
DensityPreference
WeekStartDay
```

Using backed enums reduces invalid string states and centralizes labels/category mappings.

---

# 100. Project Actions

Recommended application actions:

```text
CreateProject
UpdateProject
ChangeProjectStatus
ArchiveProject
RestoreProject
```

Each Action should represent one meaningful use case rather than generic repository CRUD.

---

# 101. Task Actions

Recommended actions:

```text
CreateTask
UpdateTask
ChangeTaskStatus
ChangeTaskPriority
ChangeTaskDueDate
DeleteTask
RestoreTask
CreateSubtask
ReorderTask
```

Label operations:

```text
AttachLabelToTask
DetachLabelFromTask
CreateLabel
DeleteLabel
```

---

# 102. Activity Recorder

Activity recording should be centralized enough that each mutation does not invent its own JSON shape.

Example service:

```text
TaskActivityRecorder
```

Responsibilities:

- create consistent TaskActivity records;
- attach user/project/task context;
- normalize old/new values;
- avoid storing unnecessary sensitive text;
- provide stable metadata conventions.

It does not determine business state.

---

# 103. Project Progress Calculator

Example service:

```text
ProjectProgressCalculator
```

Input:

```text
Project
```

Output:

```text
eligible_count
completed_count
progress_percent
```

The calculation remains derived and testable.

---

# 104. Dashboard Query Service

Example:

```text
DashboardQueryService
```

Responsibilities:

- resolve selected date period;
- return current-state KPI counts;
- return period event counts;
- return chart series;
- return attention lists;
- preserve exact metric definitions.

It does not mutate domain state.

---

# 105. Analytics Query Service

Example:

```text
AnalyticsQueryService
```

Responsibilities:

- created/completed series;
- throughput;
- status distribution;
- project contribution;
- lead/cycle time;
- time in status;
- activity heatmap;
- project progress history.

For a small personal database, these queries can run directly against PostgreSQL without a warehouse or analytics service.

---

# 106. Search Query Service

Example:

```text
SearchQueryService
```

Initial implementation:

- PostgreSQL `ILIKE` / full-text capabilities as needed;
- strict user scoping;
- capped result sets;
- project and task result categories.

No Elasticsearch, Meilisearch, Algolia, or external search service is required for the initial product.

---

# 107. HTTP Route Model

Whether implemented as conventional server routes, Inertia requests, or JSON API endpoints, the domain actions should map cleanly.

Conceptual routes:

```text
GET    /dashboard
GET    /my-work
GET    /analytics
GET    /activity
GET    /search

GET    /projects
POST   /projects
GET    /projects/{project}
PATCH  /projects/{project}
POST   /projects/{project}/status
POST   /projects/{project}/archive
POST   /projects/{project}/restore

GET    /projects/{project}/board
GET    /projects/{project}/tasks
GET    /projects/{project}/analytics
GET    /projects/{project}/activity

POST   /projects/{project}/tasks
GET    /tasks/{task}
PATCH  /tasks/{task}
DELETE /tasks/{task}
POST   /tasks/{task}/restore
POST   /tasks/{task}/status
POST   /tasks/{task}/priority
POST   /tasks/{task}/subtasks
POST   /tasks/{task}/labels
DELETE /tasks/{task}/labels/{label}

POST   /projects/{project}/board/reorder

GET    /settings
PATCH  /settings/preferences
```

Explicit lifecycle/action routes are preferable to letting arbitrary clients mutate every field without domain rules.

---

# 108. Validation Rules

Examples:

## Project

```text
name required, max 160
key required, regex ^[A-Z0-9]{2,10}$
key unique per user
start_on nullable date
target_on nullable date and preferably >= start_on
```

## Task

```text
title required, max 300
status valid TaskStatus
priority valid TaskPriority
due_on nullable date
project owned by authenticated user
parent owned by user
parent belongs to same project
parent itself has no parent
```

## Label

```text
name required, max 80
normalized name unique per user
```

---

# 109. Authorization

Even a personal deployment should use authorization consistently.

Policies:

```text
ProjectPolicy
TaskPolicy
LabelPolicy
```

Rules:

- users can only access their own projects;
- users can only access tasks through owned projects;
- users can only attach their own labels;
- cross-user IDs should not expose data.

A resource owned by another user may return `404` rather than revealing its existence.

---

# 110. Authentication

Authentication is required for a deployed personal application.

The exact deployment stack can determine the final first-party auth setup, but PlanOps should remain compatible with normal Laravel session authentication.

The product does not require OAuth providers, SSO, organizations, roles, or invitation systems for the baseline.

---

# 111. No Queue Requirement for Core PlanOps

The corrected PlanOps architecture removes a major source of unnecessary infrastructure.

Core operations are synchronous:

- create project;
- create task;
- change status;
- record history;
- query dashboard;
- query analytics.

No permanent queue worker is required for the core application.

This is especially useful for a zero-cost deployment strategy.

Queues can be introduced later only for genuinely asynchronous features such as large exports or integrations.

---

# 112. No Scheduler Requirement for Core PlanOps

PlanOps does not need a minute-by-minute scheduler for:

- reminders;
- recurrence;
- work sessions;
- daily plan creation.

Overdue state is computed when queried.

Analytics are computed from stored events.

This substantially simplifies hosting.

---

# 113. No WebSocket Requirement

The first version is single-user and does not require collaborative real-time updates.

Normal HTTP navigation/mutations are enough.

Board status changes should update optimistically or after a standard request.

WebSockets would add infrastructure without solving a current requirement.

---

# 114. Caching Strategy

Start without complex caching.

Potential short-lived caching later:

- global dashboard aggregates;
- project analytics;
- yearly heatmap.

Only introduce it after profiling shows query cost is meaningful.

For a personal dataset, well-indexed PostgreSQL queries are expected to be sufficient for a long time.

---

# 115. Pagination

Paginate large list endpoints.

Recommended initial defaults:

```text
Tasks: 50 rows
Activity: 50 events
Search: 20 per result type
Projects: pagination optional until needed
```

Board may load all active top-level tasks for one project when project size remains small.

If project boards become large, introduce lazy loading or status-specific pagination rather than prematurely optimizing.

---

# 116. Error Handling

User-facing errors should be specific and actionable.

Examples:

Bad:

> Something went wrong.

Better:

> This task could not be updated because the project no longer exists.

Validation errors stay next to the relevant field.

Unexpected errors receive a request/correlation identifier in production logs without leaking stack traces.

---

# 117. Security Baseline

PlanOps should include normal Laravel production security practices:

- authenticated routes;
- CSRF protection for session-based mutations;
- authorization policies;
- server-side validation;
- mass-assignment protection;
- secure cookies under HTTPS;
- production debug disabled;
- secrets in environment configuration;
- escaped/sanitized rendered user content;
- rate limiting where abuse is plausible;
- dependency updates;
- database backups according to hosting capability.

No special enterprise security platform is required.

---

# 118. Privacy

PlanOps may contain sensitive descriptions of professional projects.

Therefore:

- logs should not dump full task descriptions by default;
- activity history should avoid copying full descriptions into old/new values;
- analytics should operate on IDs/statuses/timestamps where possible;
- error telemetry should not capture arbitrary task body content unnecessarily.

---

# 119. Testing Strategy

PlanOps should be built test-first around domain behavior, not only CRUD endpoints.

Testing layers:

1. unit tests;
2. feature tests;
3. database/invariant tests;
4. frontend interaction tests;
5. accessibility checks;
6. end-to-end critical flows.

---

# 120. Unit Tests

Priority unit tests:

## Task status behavior

- first transition to In Progress sets `first_started_at`;
- subsequent In Progress transitions do not overwrite it;
- Done sets `completed_at`;
- reopening clears current `completed_at`;
- Cancelled sets `cancelled_at`;
- reopening Cancelled clears it;
- Overdue is computed correctly.

## Project progress

- cancelled tasks excluded;
- subtasks excluded;
- Done tasks counted;
- zero-scope behavior defined.

## Analytics

- distinct completed task counting;
- reopened behavior;
- date-period boundaries;
- project contribution denominator;
- cycle/lead time exclusions.

---

# 121. Feature Tests

Critical feature scenarios:

- user can create project;
- duplicate project key rejected;
- user can create task;
- task gets next project number atomically;
- task creation creates activity;
- status change updates task and activity;
- subtask cannot have subtask child;
- parent and child project mismatch rejected;
- board query groups tasks by status;
- filters respect user ownership;
- cross-user access rejected;
- soft deletion removes task from active views;
- dashboard metrics match seeded history.

---

# 122. Accessibility Tests

Automated accessibility testing should complement manual checks.

Critical manual scenarios:

- complete task creation with keyboard only;
- change task status without dragging;
- navigate board cards by keyboard;
- visible focus in light/dark themes;
- status understandable without color;
- chart values available without relying only on visual geometry;
- dialogs trap and return focus correctly;
- reduced-motion preference respected.

---

# 123. End-to-End Golden Scenario

A core E2E flow:

1. sign in;
2. create project `PlanOps` with key `PLAN`;
3. create `PLAN-1`;
4. create two subtasks;
5. move `PLAN-1` from Not Started to In Progress;
6. verify activity event;
7. move task to In Review;
8. mark one subtask Done;
9. mark parent Done;
10. verify project progress;
11. open Dashboard;
12. verify completion appears in current period;
13. open Analytics;
14. verify Created vs Completed data;
15. reopen task;
16. verify reopen event and current project progress change.

This scenario validates the central PlanOps promise end to end.

---

# 124. Performance Expectations

For the personal application, targets should prioritize responsiveness rather than massive scale.

Reasonable goals under normal free-tier hosting conditions:

- normal task mutation feels immediate after server response;
- project board loads quickly for hundreds of tasks;
- dashboard queries remain acceptable for thousands of activity records;
- search returns small result sets quickly;
- no N+1 query patterns in project/task lists.

Do not design for millions of concurrent users.

---

# 125. Product Module Summary

## Dashboard

Purpose: current overview and period progress.

## My Work

Purpose: cross-project execution view.

## Projects

Purpose: organize work by outcome/context.

## Board

Purpose: visual workflow management within a project.

## Tasks/Subtasks

Purpose: represent and decompose work.

## Activity

Purpose: historical traceability.

## Analytics

Purpose: understand work trends from recorded facts.

## Search

Purpose: quickly locate tracked work.

## Settings

Purpose: personal display/time preferences.

---

# 126. Feature Priority Model

## P0 — Product identity

These define PlanOps and must work extremely well:

- authentication;
- Projects;
- Tasks;
- Subtasks;
- fixed statuses;
- status transitions;
- priorities;
- due dates;
- labels;
- activity history;
- Project Board;
- My Work;
- project progress;
- Dashboard Today/Week/Month/Year;
- core analytics;
- search;
- responsive and accessible controls.

## P1 — Product depth

Useful enhancements after P0 quality:

- richer saved filters;
- more analytics drill-down;
- task restoration UI;
- project archive experience;
- keyboard shortcuts;
- command palette;
- data export;
- better project attention indicators;
- chart/table toggles;
- customizable dashboard widget ordering.

## P2 — Optional expansion

Only after real use proves demand:

- custom workflows;
- milestones;
- task dependencies;
- attachments;
- comments/notes timeline;
- recurring tasks;
- reminders;
- GitHub/GitLab integrations;
- external calendar links;
- AI summaries/suggestions;
- team collaboration;
- assignments;
- notifications;
- public API.

P2 is not part of the core baseline.

---

# 127. Features Explicitly Deferred

The following are intentionally absent from the initial core architecture:

```text
DailyPlan
DailyPlanItem
WorkSession
Timer
CapacityRule
RecurringTask
Reminder
Sprint
StoryPoint
Epic
Team
Assignee
Comment threads
File attachments
Custom workflow builder
Dependency graph
WebSocket collaboration
AI planning
```

This is not a missing-features list. It is a scope protection list.

---

# 128. Future: Custom Workflows

If later justified, PlanOps could evolve from fixed status enums into user-defined workflows.

That future model would require:

- workflow statuses table;
- stable status categories;
- ordering;
- project/workflow association;
- migration of analytics to category semantics;
- safe status deletion rules;
- transition handling.

Because this adds substantial complexity, it should not be implemented before the fixed workflow proves limiting in real usage.

---

# 129. Future: Milestones

Milestones could divide a project into phases such as:

```text
Research
Implementation
Validation
Release
```

This is potentially useful, but Project → Task → Subtask is sufficient for the baseline.

---

# 130. Future: Dependencies

A later version may support:

```text
Task A blocks Task B
Task B depends on Task A
```

If added, dependency state should be derived, not manually duplicated.

This feature is intentionally omitted until real workflows require it.

---

# 131. Future: Integrations

Potential integrations:

- GitHub pull requests;
- GitHub commits;
- GitLab merge requests;
- calendar links;
- Slack/Teams notifications;
- import/export with Jira or Linear.

Integrations must never replace PlanOps's explicit task status as the authoritative current-state model unless a future product decision explicitly changes that principle.

---

# 132. Future: AI

AI may eventually assist with:

- summarizing recent project activity;
- suggesting a task title from text;
- grouping analytics observations;
- suggesting stale tasks to review;
- generating a weekly progress summary.

AI must not silently:

- complete tasks;
- cancel tasks;
- invent work history;
- claim hours worked;
- rewrite activity events;
- change project status.

AI remains advisory.

---

# 133. Data Export

A useful P1 portability feature is export.

Recommended formats:

## Projects CSV

Project metadata and current progress.

## Tasks CSV

```text
key
project
parent
title
status
priority
due_on
labels
created_at
updated_at
```

## Activity JSON/CSV

Historical domain events.

Exports help keep PlanOps from becoming a data silo.

---

# 134. Product Naming and Terminology

Canonical product vocabulary:

```text
Project
Task
Subtask
Status
Priority
Label
Activity
Dashboard
Analytics
My Work
Board
```

Avoid mixing synonyms in the UI such as Issue, Ticket, Card, Work Item, and Task for the same domain object.

The implementation may use internal technical terminology, but the user-facing product should consistently say **Task**.

---

# 135. Proposed Product Tagline

Possible concise positioning:

> **PlanOps — Track the work. See the progress.**

Alternative:

> **PlanOps — Your personal project operations board.**

The exact marketing tagline is secondary to the domain terminology.

---

# 136. Example Real Usage

## Project

```text
Angular Migration Factory
Key: AMF
Status: ACTIVE
Target: 2026-09-30
```

## Tasks

```text
AMF-42 Fix Angular 20 → 21 feasibility failure    IN_PROGRESS  HIGH
AMF-43 Improve migration dashboard                NOT_STARTED  MEDIUM
AMF-44 Validate PDF report                        IN_REVIEW    MEDIUM
AMF-45 Investigate Node compatibility             DONE         HIGH
```

## AMF-42 Subtasks

```text
AMF-46 Reproduce G05 failure                      DONE
AMF-47 Capture command evidence                    DONE
AMF-48 Inspect feasibility rule                    IN_PROGRESS
AMF-49 Add regression test                         NOT_STARTED
```

## Today Dashboard

```text
In Progress: 2
In Review: 1
Blocked: 0
Completed today: 3
Started today: 2
```

## Weekly Analytics

```text
Created: 11
Completed: 14
Moved to review: 6
Reopened: 1
Median cycle time: 2.4 days
```

This example demonstrates PlanOps without timers, automatic monitoring, or daily planning records.

---

# 137. Research Verification: Jira

Current Jira documentation confirms several patterns that support PlanOps's design:

1. work items are organized into parent/child hierarchies, with standard work and subtasks as distinct hierarchy levels;
2. workflows are composed of statuses and transitions;
3. board columns visually represent status/workflow state;
4. Jira groups detailed statuses into broader categories useful for reporting;
5. Jira dashboards include created-vs-resolved, average age, average time in status, and resolution-time style reporting.

PlanOps adopts the underlying principles but removes Jira-specific organizational complexity.

Research references:

- Atlassian Support, “What are work types?”  
  https://support.atlassian.com/jira-cloud-administration/docs/what-are-issue-types/
- Atlassian Support, “Workflows and statuses for boards in business spaces”  
  https://support.atlassian.com/jira-software-cloud/docs/workflows-and-statuses-for-boards-in-business-projects/
- Atlassian Support, “What is a board in software spaces?”  
  https://support.atlassian.com/jira-software-cloud/docs/what-is-a-jira-software-board/
- Atlassian Support, “Dashboard gadgets”  
  https://support.atlassian.com/jira-cloud-administration/docs/use-dashboard-gadgets/

Accessed for this specification on 2026-08-20.

---

# 138. Research Verification: Linear

Linear provides a useful second reference because its model is more focused than Jira.

Current Linear documentation supports these PlanOps decisions:

- Issues are the fundamental day-to-day unit of work.
- Issues move through ordered workflow statuses.
- Projects group issues toward larger outcomes.
- Project status is updated manually rather than being automatically completed when all issues are done.
- Project graphs use issue history to visualize scope/progress.

PlanOps adopts the clarity of these concepts while remaining a personal rather than team-oriented tool.

Research references:

- Linear Docs, “Concepts”  
  https://linear.app/docs/conceptual-model
- Linear Docs, “Issue status”  
  https://linear.app/docs/configuring-workflows
- Linear Docs, “Projects”  
  https://linear.app/docs/projects
- Linear Docs, “Project status”  
  https://linear.app/docs/project-status
- Linear Docs, “Project graph”  
  https://linear.app/docs/project-graph

Accessed for this specification on 2026-08-20.

---

# 139. Research Verification: Accessibility

WCAG 2.2 directly influences PlanOps board and control design.

The important implications for this product are:

- dragging functionality requires a non-dragging alternative when dragging is not essential;
- pointer targets must satisfy minimum target sizing/spacing requirements;
- keyboard and focus behavior must remain clear;
- predictable controls reduce interaction complexity.

Research references:

- W3C, Web Content Accessibility Guidelines (WCAG) 2.2  
  https://www.w3.org/TR/WCAG22/
- W3C WAI, “What's New in WCAG 2.2”  
  https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/

Accessed for this specification on 2026-08-20.

---

# 140. Design Decisions from Research

## Decision 1 — Keep Project → Task → Subtask

Jira supports more hierarchy, but PlanOps does not need enterprise hierarchy for its personal use case.

## Decision 2 — Keep statuses explicit

Both Jira and Linear center workflows on status transitions.

PlanOps follows the same principle.

## Decision 3 — Keep project status manual

Linear explicitly keeps project lifecycle status manual even if project issues are complete.

PlanOps follows this because the user, not an algorithm, decides whether a project is finished.

## Decision 4 — Derive analytics from history

Jira's reporting model demonstrates the value of created/completed, age, and status-duration metrics.

PlanOps derives these from TaskActivity instead of introducing timers.

## Decision 5 — Do not require drag

WCAG 2.2 makes this both an accessibility requirement and a better interaction design.

---

# 141. Architectural Decision Records

## ADR-001 — PlanOps is a modular monolith

**Decision:** one Laravel application and one relational database.  
**Reason:** project scale does not justify distributed services.  
**Consequence:** deployment, transactions, and debugging remain simple.

## ADR-002 — Task is the fundamental unit of work

**Decision:** Task is the central domain entity.  
**Reason:** the product exists to track work state.  
**Consequence:** dashboards and analytics revolve around task events.

## ADR-003 — Project lifecycle is manual

**Decision:** task completion never automatically completes a project.  
**Reason:** project completion is a user judgment.  
**Consequence:** progress and project status can legitimately differ.

## ADR-004 — Task status is explicit and manual

**Decision:** PlanOps does not infer current task status.  
**Reason:** the system must represent what the user declares.  
**Consequence:** analytics describe recorded workflow state, not invisible activity.

## ADR-005 — Overdue is computed

**Decision:** no OVERDUE task status.  
**Reason:** overdue depends on date and terminal state.  
**Consequence:** no duplicated state to synchronize.

## ADR-006 — Activity history is append-only

**Decision:** meaningful mutations create TaskActivity events.  
**Reason:** historical analytics require reliable transition facts.  
**Consequence:** history becomes a first-class source for reports.

## ADR-007 — Project progress uses top-level tasks

**Decision:** subtasks do not directly weight project percentage.  
**Reason:** different subtask counts would distort project weighting.  
**Consequence:** tasks show their own subtask progress separately.

## ADR-008 — Fixed workflow first

**Decision:** no custom workflow builder in the baseline.  
**Reason:** one user's workflow is known and fixed statuses dramatically reduce complexity.  
**Consequence:** custom workflows remain a future extension.

## ADR-009 — No time tracking in the core

**Decision:** no WorkSession/timer model.  
**Reason:** the user wants manual work-state tracking, not activity monitoring.  
**Consequence:** PlanOps never presents elapsed workflow time as worked hours.

## ADR-010 — No background infrastructure required for core behavior

**Decision:** no required queue worker, scheduler, or WebSocket service.  
**Reason:** core actions and analytics are request-driven.  
**Consequence:** zero-cost deployment becomes simpler.

## ADR-011 — Accessibility is mandatory

**Decision:** drag has alternatives, statuses include text, keyboard access is first-class, and layout density is user-selectable.  
**Reason:** a dense Jira-like interface can otherwise become difficult to use.  
**Consequence:** accessibility is part of component acceptance criteria.

## ADR-012 — Hosting is not allowed to reshape domain truth

**Decision:** deployment provider choices may change infrastructure adapters but not Project/Task/Activity semantics.  
**Reason:** product architecture should remain portable.  
**Consequence:** the separate zero-cost stack review can change database/hosting/frontend delivery without redesigning PlanOps.

---

# 142. Implementation Phases

This is a product decomposition, not a sprint schedule.

## Phase 0 — Foundation

- Laravel 13 / PHP 8.3 project baseline;
- authentication;
- database;
- user preferences;
- application shell/navigation;
- theme/density foundation;
- test infrastructure.

## Phase 1 — Projects

- project create/edit;
- project status;
- project keys;
- project list;
- project overview shell;
- archive/restore.

## Phase 2 — Tasks and Subtasks

- task creation;
- project-local numbering;
- task detail;
- priority;
- due date;
- labels;
- subtask hierarchy;
- soft deletion.

## Phase 3 — Workflow and Activity

- status enum;
- status changes;
- status timestamps;
- automatic TaskActivity;
- task timeline;
- project/global activity feeds.

## Phase 4 — Execution Views

- Project Board;
- non-drag status controls;
- board reordering;
- project task list;
- My Work;
- filters/sorting;
- global search.

## Phase 5 — Dashboard

- Today;
- Week;
- Month;
- Year;
- KPI cards;
- current-work sections;
- created/completed chart;
- project contribution;
- status distribution.

## Phase 6 — Analytics

- throughput;
- lead time;
- cycle time;
- time in status;
- activity heatmap;
- project analytics;
- project progress history.

## Phase 7 — UX and Accessibility Hardening

- keyboard workflows;
- focus management;
- chart accessibility;
- responsive layouts;
- comfortable/compact density;
- empty states;
- reduced motion;
- manual WCAG critical-flow review.

## Phase 8 — Production Hardening

- authorization audit;
- security configuration;
- logging;
- backup/export strategy;
- performance profiling;
- zero-cost deployment implementation;
- production verification.

---

# 143. Acceptance Criteria — Product Core

PlanOps is not considered functionally complete until the user can perform the following coherent journey:

1. create a project;
2. create top-level tasks;
3. add subtasks;
4. set priority and due dates;
5. assign labels;
6. move tasks through the workflow;
7. see those changes recorded automatically;
8. manage the project through a board and list;
9. see active work across projects in My Work;
10. see project progress derived from tasks;
11. see what happened today;
12. see weekly, monthly, and yearly progress;
13. inspect created-vs-completed and status trends;
14. search tracked work;
15. perform essential operations without drag-and-drop;
16. use the application in dark/light/system mode;
17. use a core workflow with keyboard navigation.

---

# 144. Acceptance Criteria — Trustworthy Analytics

Every displayed metric must document:

- its data source;
- whether it is a current snapshot or period event metric;
- whether subtasks are included;
- how reopened tasks behave;
- timezone boundaries;
- denominator rules;
- no-data behavior.

No metric may be labeled as hours worked or productivity unless the product later gains an explicit source of actual effort data.

---

# 145. Acceptance Criteria — Project Progress

Given:

```text
Task A = DONE
Task B = DONE
Task C = IN_PROGRESS
Task D = CANCELLED
Task A has 10 subtasks
Task B has 1 subtask
```

Project progress must be:

```text
2 / 3 = 66.67%
```

Task A's many subtasks must not make Task A more heavily weighted than Task B.

---

# 146. Acceptance Criteria — Reopen

Given a task:

```text
IN_PROGRESS → DONE → IN_PROGRESS
```

PlanOps must:

- record both transitions;
- set completed timestamp on Done;
- clear current completed timestamp on reopen;
- count the task as reopened in the relevant period;
- reduce current project progress if it was previously counted as Done;
- retain historical completion in TaskActivity.

---

# 147. Acceptance Criteria — Board Accessibility

The user can move a card from `NOT_STARTED` to `IN_PROGRESS` in at least two ways:

1. drag-and-drop when supported;
2. task/card status control without dragging.

The second method must be fully keyboard operable.

---

# 148. Acceptance Criteria — Dashboard Periods

When switching:

```text
Today → Week → Month → Year
```

PlanOps must:

- update period metrics;
- update chart buckets appropriately;
- preserve current-state KPI semantics;
- show the active period visibly;
- use configured timezone boundaries.

---

# 149. Risks and Mitigations

## Risk: Becoming a Jira clone

**Mitigation:** maintain the explicit non-goals and P2 boundary.

## Risk: Dashboard metrics become misleading

**Mitigation:** exact formulas and labels; no productivity claims.

## Risk: Too many board columns on small screens

**Mitigation:** list-first mobile experience and horizontal scrolling as secondary behavior.

## Risk: Activity history becomes noisy

**Mitigation:** record domain events, not every UI/database change.

## Risk: Analytics queries become complex

**Mitigation:** small relational model, indexes, dedicated query service, profile before caching/materialization.

## Risk: Accessibility degrades as board interactions get richer

**Mitigation:** non-drag alternatives and keyboard acceptance tests are release criteria.

## Risk: Free hosting constraints influence product design

**Mitigation:** keep core synchronous and infrastructure-light; hosting review remains separate from domain architecture.

---

# 150. Success Criteria for PlanOps as a Portfolio Project

PlanOps should demonstrate more than CRUD.

It should visibly showcase:

- domain modeling;
- state transitions;
- audit/event history;
- derived analytics;
- relational design;
- transaction boundaries;
- authorization;
- accessible rich UI;
- board interaction;
- filtering/search;
- charting;
- testable business rules;
- deployment discipline.

A recruiter or engineer looking at the project should quickly understand that it is a small but coherent product, not a tutorial task manager.

---

# 151. Final Product Statement

> **PlanOps is a personal work-operations system for tracking projects and the tasks inside them. The user explicitly records what work exists and manually moves each task through a clear workflow—Backlog, Not Started, In Progress, In Review, Blocked, Done, or Cancelled. Tasks can be decomposed into subtasks, prioritized, labeled, and given due dates. Every meaningful change is preserved in an automatic activity history. PlanOps then transforms that history and current task state into project boards, cross-project work views, project progress, and dashboards showing what happened today and how work evolved across the week, month, and year. It does not monitor the user's computer or pretend to know time spent; it reports only what the user explicitly recorded. The result is a lightweight, personal alternative to the useful work-tracking parts of Jira and Linear without their team/enterprise overhead.**

---

# 152. Specification Boundary

This document is the new authoritative product/design baseline for the corrected PlanOps concept.

The next design artifact should **not** add more product brainstorming by default.

The appropriate next artifacts are:

1. a verified zero-cost technical stack and deployment architecture;
2. an exact Laravel database/migration and class contract specification;
3. a screen-by-screen UI specification/wireframe set;
4. an implementation plan/backlog derived from this approved product spec.

Those artifacts must preserve the product truth defined here unless the product requirements themselves are deliberately changed.

---

# 153. Self-Review Result

This specification was reviewed for:

- placeholder text;
- contradictory status rules;
- Project vs Task responsibility confusion;
- accidental reintroduction of time tracking/daily planning;
- ambiguous analytics denominators;
- subtask weighting problems;
- project lifecycle automation;
- drag-only interaction;
- infrastructure over-design;
- hosting coupling.

The product requirements contain no intentionally unresolved implementation placeholders.

The most important scope decision is explicit:

> **PlanOps tracks declared project/task state and derives progress from that state and its history. It does not track the user's actual physical/computer activity.**

