# PlanOps audit context

This context-only dossier records the DYX-007.3 security-scope map reviewed from the current PlanOps
workspace. It maps trusted and untrusted inputs, access scopes, route bindings, policy boundaries, mutation
actions, notification delivery, activity recording, and open verification questions. It does not serve as a
security verdict or implementation plan.

## Covered function records

- `functions/route-bindings.md`
- `functions/project-access-scopes.md`
- `functions/task-and-related-scopes.md`
- `functions/policy-role-resolution.md`
- `functions/report-and-export-scopes.md`
- `functions/dashboard-analytics-queries.md`
- `functions/search-activity-my-work.md`
- `functions/project-query-surfaces.md`
- `functions/task-query-surfaces.md`
- `functions/collaboration-query-surfaces.md`
- `functions/global-surface-controllers.md`
- `functions/project-task-controllers.md`
- `functions/collaboration-controllers.md`
- `functions/notification-controller.md`
- `functions/request-boundaries.md`
- `functions/assign-task.md`
- `functions/reorder-tasks.md`
- `functions/invitation-actions.md`
- `functions/activity-recorder.md`
- `functions/notification-delivery.md`
- `functions/project-member-controller.md`
- `functions/mutation-surface-map.md`

## Review boundary

The records cover `app/`, `routes/`, the relevant feature/unit tests, and the DYX-007.3 release contract.
They distinguish ordinary project visibility from detailed-report/export visibility, preserve the legacy
`user_id` compatibility branches as explicit assumptions, and trace nested identifiers through route binding,
controllers, policies, query objects, transactional actions, invitation lifecycle, notification delivery, and
task activity recording. Invitation acceptance establishes an active-user check and normalized email match
before membership mutation (`app/Domain/Collaboration/Actions/AcceptProjectInvitation.php:L43-L60`).

Open questions from the context pass were converted into targeted regression work where the authority
document supplied a clear contract: deactivated-account lifecycle enforcement and generic public invitation
previews. Remaining PostgreSQL/concurrency evidence stays an explicit release-gate exception.
