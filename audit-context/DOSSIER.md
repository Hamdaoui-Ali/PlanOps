# PlanOps audit context

This context-only dossier records the DYX-007.3 security-scope map reviewed from the PlanOps branch at
`116983b` before the follow-up lifecycle and invitation-preview changes. It maps trusted and untrusted
inputs, access scopes, route bindings, policy boundaries, mutation actions, and open verification questions.
It does not serve as a security verdict; the implementation and regression tests that followed are recorded
in normal source and test history.

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

## Review boundary

The records cover `app/`, `routes/`, the relevant feature/unit tests, and the DYX-007.3 release contract.
They distinguish ordinary project visibility from detailed-report/export visibility, preserve the legacy
`user_id` compatibility branches as explicit assumptions, and trace nested identifiers through route binding,
controllers, policies, query objects, and transactional actions.

Open questions from the context pass were converted into targeted regression work where the authority
document supplied a clear contract: deactivated-account lifecycle enforcement and generic public invitation
previews. Remaining PostgreSQL/concurrency evidence stays an explicit release-gate exception.
