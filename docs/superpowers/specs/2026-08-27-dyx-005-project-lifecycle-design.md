# DYX-005 Project Lifecycle and Projects Console Design

## Goal

Make Projects the first real PlanOps work surface: a user can create a project, scan owned projects, edit project metadata, change lifecycle status deliberately, and archive or restore a project without losing its tasks or activity history.

The accepted visual target is Product Design ideation result 3 from the 2026-08-27 set: a focused dark work console with a narrow navigation rail, a row-based project table, visible keyboard focus, and one clear `New project` action. The reference was generated at 1440 × 1024 and remains a local Product Design reference outside the repository.

## Scope

This slice implements project creation and editing, project-local key validation and per-user uniqueness, manual lifecycle status changes, independent archive and restore actions, owner-scoped project index filtering and sorting, the project routes, the Projects index/create/edit screens, and a responsive application shell that follows the accepted target.

Project overview, board, task capture, task metadata, analytics, and project activity remain later dependency slices. Until DYX-011 adds the overview route, the Projects row uses the edit screen as its temporary `Open project` destination.

## Product rules

- Project status is manual and uses only `PLANNED`, `ACTIVE`, `ON_HOLD`, `COMPLETED`, and `CANCELLED`.
- Archive is separate from status and is represented by `archived_at`.
- Project names are required and capped at 80 characters at the request boundary.
- Project keys are 2–10 uppercase ASCII letters or numbers, contain no spaces, and are unique per user.
- A project key may change only while the project has never contained a task, including soft-deleted tasks.
- Target date cannot precede start date.
- Archive and restore never delete, archive, or mutate tasks or activity rows.
- Project status never changes automatically when tasks reach `DONE`.
- Every query and action is authorized for the authenticated owner; cross-user project identifiers resolve as 404.

## Visual system

| Token | Value | Use |
| --- | --- | --- |
| Canvas | `#0E161B` | application background |
| Rail | `#15232A` | navigation surface |
| Surface | `#101D24` | table and form surfaces |
| Border | `#263841` | dividers and control outlines |
| Text | `#D7E1E4` | primary copy |
| Muted | `#8EA3A8` | metadata and secondary copy |
| Focus / progress | `#B7D96B` | keyboard focus and progress only |
| Supporting accent | `#68B8C0` | links and selected navigation |
| Warning | `#E9B66A` | `ON_HOLD` and attention |
| Danger | `#F08C84` | destructive action and errors |

No gradients, glass, glow, decorative illustrations, or color-only status meanings. Use the existing Figtree family, 14–16px body copy, 34–40px page titles, restrained 6–10px radii, hairline row separators, and controls at least 40px high.

Use the Phosphor regular web icon family as a local dependency. Icons are decorative beside action text and never replace accessible labels.

## Information architecture

Desktop uses a 216px left rail with PlanOps, Dashboard, My Work, Projects, Analytics, Activity, Settings, and a collapse affordance. The main area contains the `Projects` title, `New project`, `Find a project`, Active/Archived view controls, `All statuses`, `Recently updated`, and a single project ledger with name, key, status, scope progress, percentage, target date, and open affordance.

The default view is active projects sorted by recently updated. Search matches project name or key. Filtered empty results offer a reset path. Mobile keeps the title and primary action visible, moves navigation behind a labeled menu button, stacks filters, and converts table rows into two-line list items without removing status, progress, or target date.

## Workflows

- Create: `New project` opens `/projects/create`; the form collects name, key, description, start date, target date, and status. Key input is normalized to uppercase before validation.
- Edit: `/projects/{project}/edit` edits metadata. Once any task exists, including deleted history, the key is read-only with a reason.
- Status: a separate named status form calls an explicit Action; tasks never auto-complete projects.
- Archive/restore: separate forms operate on `archived_at`; archive confirmation is keyboard accessible and restore is direct.

## Application architecture

`ProjectIndexQuery` owns filtering, sorting, pagination, and derived top-level task counts. Each use case has one Action: `CreateProject`, `UpdateProject`, `ChangeProjectStatus`, `ArchiveProject`, or `RestoreProject`. `ProjectPolicy` exposes `viewAny`, `view`, `create`, `update`, `changeStatus`, `archive`, and `restore`, all based on `user_id`. Requests own HTTP validation and authorization; Actions repeat domain invariants for non-HTTP callers. The controller composes these units and route binding scopes `{project}` to the authenticated owner.

Progress is derived from non-cancelled, top-level, non-deleted tasks. A project with zero eligible tasks displays `0%` and `No active scope`.

## Testing and verification

Tests cover validation, uniqueness, key locking, date ordering, manual status, archive/restore preservation, policy and 404 isolation, index filters/sorts/progress/pagination, HTTP workflows, responsive rendering, keyboard focus, status text, and archive confirmation. The selected image is a visual source, not a runtime asset; the UI is code-native HTML/CSS with library icons.
