# DYX-003 — Membership and invitation lifecycle

**Status:** Blocked until DYX-002 is accepted

**Priority:** P0

**Dependency:** [DYX-002](DYX-002-access-policies.md)

**Source:** [Sprint 2 specification](../PlanOps_Sprint_2.md), sections 14, 15, 16, 17, 18, 19, 20, 22, 35, 36, 37, 38, 51, 52, 55, and 56.

## Goal

Deliver a secure membership lifecycle: invite, preview, accept, resend, revoke, remove, role change, reactivation, ownership transfer, and account-deletion protection.

## Files

- `app/Domain/Collaboration/Actions/`
- `app/Domain/Collaboration/Rules/`
- `app/Domain/Collaboration/Models/ProjectMembership.php`
- `app/Domain/Collaboration/Models/ProjectInvitation.php`
- `app/Domain/Collaboration/Models/ProjectEvent.php`
- invitation/member controllers and form requests
- `routes/web.php`
- `resources/views/`
- `app/Models/User.php`
- invitation and membership feature tests

## Tasks

### Task DYX-003.1

Goal: Implement the Team/Members management surface.

Files: collaboration actions/rules, Team routes/controllers/components/views, and membership tests.

Action: List active and removed memberships with role, join, and removal state. Allow Owner/Admin to invite Members; allow only Owner to change Member/Admin roles. Prevent Admin invitation of Admin and prevent any invitation with role `OWNER`.

Why: the project Team surface is the control plane for collaboration and must mirror the permission matrix.

Verification: Browser/request tests cover Owner, Admin, Member, removed member, duplicate user, and deactivated user states. Keyboard navigation and accessible status messaging are included where the browser suite exists.

Expected result: Authorized users can manage the project Team without seeing controls they cannot use or bypassing server authorization.

### Task DYX-003.2

Goal: Implement secure invitation creation and token preview.

Files: invitation action/rule/controller/request/view, invitation model, routes, and tests.

Action: Normalize emails case-insensitively; enforce one pending invitation per `(project_id, normalized_email)`; store only a secure token hash; use a seven-day expiry; rate-limit creation/resend; protect POST actions with CSRF; and return a privacy-safe public preview that reveals no project data.

Why: invitation links are an unauthenticated entry point and a high-value token surface.

Verification: Test duplicate and racing invites, expired/revoked/reused tokens, raw-token absence from logs/database responses, rate limits, CSRF, and preview privacy.

Expected result: An invitation is safe to send, safe to preview, and impossible to create repeatedly for the same pending recipient/project pair.

### Task DYX-003.3

Goal: Implement authenticated acceptance and reactivation.

Files: accept controller/action/request, membership model, invitation model, routes/views, and tests.

Action: Require authentication before acceptance; require the authenticated email to match the normalized invitation email or satisfy the documented verification rule; lock the invitation and membership rows in a deterministic order; accept once; reactivate a removed membership rather than creating a duplicate; and record `INVITATION_ACCEPTED`.

Why: concurrent acceptance must result in exactly one active membership and one durable outcome.

Verification: Concurrency tests cover two acceptance requests, email mismatch, already accepted, revoked, expired, and removed-member reactivation cases.

Expected result: A successful acceptance creates or reactivates exactly one active membership and cannot be replayed.

### Task DYX-003.4

Goal: Implement revoke, resend, removal, and role-change actions.

Files: collaboration actions/rules/controllers/requests, routes/views, and lifecycle tests.

Action: Define valid invitation transitions without adding a redundant `status` column: pending means not accepted, not revoked, and not expired. Resend rotates the token and expiry safely; revoke is terminal; removal sets `removed_at` and `removed_by_user_id`, unassigns affected tasks in the documented transaction, and role changes are restricted to Owner.

Why: explicit state transitions prevent stale links and removed-member access from becoming ambiguous.

Verification: Test each valid and invalid transition, idempotency, stale forms, removal during an assignment request, and retained audit history.

Expected result: Membership and invitation state changes are deterministic, authorized, and immediately reflected in access queries.

### Task DYX-003.5

Goal: Implement atomic ownership transfer and account-deletion protection.

Files: ownership-transfer action/rule/controller/request, `app/Models/User.php`, project/membership policies, and lifecycle tests.

Action: Lock the project and both membership rows; verify the current Owner; promote the target active member; demote the previous Owner; update `projects.owner_id`; record `OWNERSHIP_TRANSFERRED`; and reject deletion/deactivation of a user who still owns projects until each project has a new Owner.

Why: ownership transfer closes the last single-owner escape path and protects project administration from account deletion.

Verification: Test repeated transfer, invalid target, removed target, concurrent transfer, stale owner, and account deletion with and without owned projects.

Expected result: Every project always has exactly one active Owner and ownership changes are auditable and transactional.

## Acceptance criteria

- [ ] Only authorized Owner/Admin users can invite, and `OWNER` is never an invitation role.
- [ ] Invitation tokens are hashed, expire after seven days, are not logged raw, and cannot be reused.
- [ ] Pending invites are unique per project and normalized email, including under concurrent requests.
- [ ] Acceptance requires authentication and the documented email/verification match.
- [ ] Acceptance creates or reactivates exactly one active membership.
- [ ] Revoke, resend, removal, and role-change transitions are authorized, idempotent, and audited.
- [ ] Removed members lose access immediately and affected assignments follow the documented rule.
- [ ] Ownership transfer is atomic, records `OWNERSHIP_TRANSFERRED`, and prevents Owner account deletion until resolved.
- [ ] Team UI is keyboard-accessible and does not rely on hidden controls as the security boundary.

## Verification commands

```text
php artisan test --filter=Invitation
php artisan test --filter=Membership
php artisan test --filter=Ownership
php artisan route:list --except-vendor
```

Also run the documented concurrency tests against PostgreSQL; serialized SQLite tests are not sufficient evidence for row-lock behavior.

## Expected result

The project Team and invitation lifecycle are secure under direct requests, stale pages, duplicate clicks, token replay, and concurrent membership changes.

## Suggested commit boundaries

- `test: define membership and invitation lifecycle invariants`
- `feat: add secure invitation and membership actions`
- `feat: add atomic ownership transfer and account protection`
- `test: verify invitation concurrency and removal races`

## Next action

Implement invitation and membership tests after DYX-002 has a green access matrix, then build the actions around those tests.
