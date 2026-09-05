# DYX-005 — Project-scoped labels

**Status:** Ready after DYX-001 and DYX-002

**Priority:** P0

**Dependencies:** [DYX-001](DYX-001-schema-migration.md) and [DYX-002](DYX-002-access-policies.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 10, 21, 23, 26, 33, 34, 39, 57, and 60.

## Goal

Move labels from user scope to project scope without merging unrelated labels or allowing cross-project task attachments.

## Files

- label migrations and backfill command/report
- `app/Domain/Labels/Models/Label.php`
- label actions, policies, queries, and form requests
- task-label attachment actions and queries
- label views/components and task filters
- label, migration, and authorization tests

## Tasks

### Task DYX-005.1

Goal: Define the project-scoped label model and invariant.

Files: label migration/model/policy and schema/domain tests.

Action: Add `project_id` to labels; define `normalized_name`; enforce uniqueness by `(project_id, normalized_name)`; and require every attachment to reference a task in the same project as the label.

Why: members need a shared project taxonomy, but the taxonomy must not become a cross-project data channel.

Verification: Schema and model tests reject duplicate normalized names within one project and allow the same normalized name in different projects.

Expected result: A label has one project scope and cannot be attached across project boundaries.

### Task DYX-005.2

Goal: Migrate legacy user-owned labels deterministically.

Files: label backfill command/migration, preflight report, and migration tests.

Action: Infer a label's project from its attached tasks. Duplicate labels with the same normalized name across projects into separate project records. Handle unattached legacy labels explicitly: retain them in a documented quarantine/legacy state or assign them only through an approved deterministic rule; never guess a project silently.

Why: a bulk update that picks one project for every old label can leak or merge unrelated taxonomies.

Verification: Use fixtures with one project, multiple projects, duplicate names, mixed attachments, and unattached labels. Rerun the backfill and assert stable IDs/counts and no cross-project attachment.

Expected result: Every migrated label has an explainable project decision and no unrelated project data is merged.

### Task DYX-005.3

Goal: Move label reads and mutations behind membership-aware policies.

Files: `LabelPolicy`, label queries/actions/controllers, task filters, and authorization tests.

Action: Scope label lists and selectors through the current project membership. Allow active Members to read project labels; allow only Owner/Admin to create, rename, recolor, or delete them. Ensure a stale label ID cannot be used with a task from another project.

Why: label selectors are both a read surface and a mutation entry point.

Verification: Request tests cover Owner/Admin/Member, removed membership, archived projects, cross-project IDs, direct URLs, and task-list/board filter results.

Expected result: Labels are visible and mutable only within the current project and role boundary.

### Task DYX-005.4

Goal: Preserve label behavior in task experiences.

Files: task detail/list/board views, label selector/filter components, and browser/accessibility tests.

Action: Update label creation, attachment, detachment, filtering, and display to use project scope. Keep existing label names/colors where migration allows and provide a clear validation message for unresolved legacy labels.

Why: a correct database migration is incomplete if task views still query user-owned labels.

Verification: Browser/request tests exercise project switching, duplicate names, filters, keyboard selection, mobile layout, and inaccessible projects.

Expected result: Project members see a consistent label set and cannot accidentally apply another project's label.

## Acceptance criteria

- [ ] Labels have a project scope and normalized-name uniqueness within that project.
- [ ] The same normalized label name may exist independently in different projects.
- [ ] Legacy labels are migrated from task evidence or explicitly quarantined; no project is guessed silently.
- [ ] Task-label attachments always remain within one project.
- [ ] Members can read labels; only Owner/Admin can mutate them.
- [ ] Archived, removed, and cross-project requests are rejected server-side.
- [ ] Label filters and selectors use the same access scope as project/task reads.
- [ ] Backfill is repeatable and preserves explainable counts and references.

## Verification commands

```text
php artisan test --filter=Label
php artisan test --filter=Migration
php artisan test --filter=Authorization
rg -n "normalized_name|project_id|LabelPolicy|task_label|labels" app database routes tests
```

## Expected result

One consistent project label taxonomy with no cross-project leakage and a documented treatment for unattached legacy labels.

## Suggested commit boundaries

- `test: define project-scoped label invariants`
- `feat: migrate labels to project scope`
- `feat: enforce label access and task attachment boundaries`
- `test: verify legacy label backfill and cross-project isolation`

## Next action

Run the label preflight report after DYX-001, then lock the project-scope and cross-project attachment tests.
