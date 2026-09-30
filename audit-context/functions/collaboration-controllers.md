## `ProjectTeamController::show` in app/Http/Controllers/Collaboration/ProjectTeamController.php (L13-L19)

**Purpose:** Renders a project team surface for any viewer allowed by the project `view` ability.

**Inputs & Assumptions:**
- `$project`: custom-bound accessible project. Trust: semi-trusted route model.
- `$request`: authenticated viewer.
- Implicit: `view` policy is registered for Project (`AppServiceProvider.php:L27-L32`).

**Outputs & Effects:**
- Authorizes then loads active memberships/users and pending invitations on the project relation, returning the
  team view (`ProjectTeamController.php:L13-L19`). No writes.

**Cross-Function Dependencies:** `ProjectPolicy::view`/`role`, `Project::activeMemberships`, invitation
relation filter at `ProjectTeamController.php:L17-L18`.

**Open Questions:**
- Invitation list filters accepted/revoked but not expiry at the controller relation
  (`ProjectTeamController.php:L17-L18`); the intended UI treatment of expired pending rows is not established here.

---

## `ProjectTeamWorkController::show` in app/Http/Controllers/Collaboration/ProjectTeamWorkController.php (L14-L22)

**Purpose:** Gates and renders manager-only Team Work data.

**Inputs & Assumptions:**
- `$project`: custom-bound accessible project.
- `$request`: authenticated viewer.
- `$work`: internal TeamWork query.

**Outputs & Effects:**
- Applies `viewTeamWork`, calls `TeamWorkQuery::for`, and returns the view (`ProjectTeamWorkController.php:L14-L22`).
  No writes.

**Cross-Function Dependencies:** `ProjectPolicy::viewTeamWork`/`role`, `TeamWorkQuery::for`.

**Open Questions:**
- Controller ability is manager-only while TeamWork query scope is ordinary accessible-by; the composition is
  intentional in visible code but not enforced by a shared scope.

---

## `ProjectInvitationController::show` in app/Http/Controllers/Collaboration/ProjectInvitationController.php (L19-L23)

**Purpose:** Renders public invitation preview by token.

**Inputs & Assumptions:**
- `$token` (string): URL token. Trust: untrusted public input.
- Implicit: stored `token_hash` is SHA-256 of the plain token; `project` relation is available
  (`ProjectInvitationController.php:L21-L22`).

**Outputs & Effects:**
- Hashes token, selects one invitation/project, and renders the preview (`ProjectInvitationController.php:L19-L23`).
  No writes.

**Block-by-Block:**

```php
// L21-L23
$invitation = ProjectInvitation::query()->with('project')->where('token_hash', hash('sha256', $token))->first();
return view(...);
```
- **What:** Performs a hash equality lookup and passes nullable invitation to the view.
- **Why here:** Public preview needs to resolve a token without requiring authentication.
- **Assumes:** hash storage and token generation agree; view handles a null/non-pending row.
- **Establishes:** preview model or null, not an accepted membership.

**Cross-Function Dependencies:** `ProjectInvitation::project` relation; token generation in
`InviteProjectMember`/`ResendProjectInvitation`.

**Open Questions:**
- The method does not call `isPending()`/`isExpired()`; nothing found in `ProjectInvitationController.php:L19-L23`
  establishes how accepted, revoked, or expired previews are rendered.

---

## `ProjectInvitationController::accept` in app/Http/Controllers/Collaboration/ProjectInvitationController.php (L26-L31)

**Purpose:** Accepts a token for the authenticated request user through the invitation Action.

**Inputs & Assumptions:**
- `$request`: authenticated user from route group (`routes/web.php:L68-L70`).
- `$token` (string): untrusted route input.
- `$accept`: internal Action.

**Outputs & Effects:**
- Delegates token validation/membership creation, then redirects to projects index
  (`ProjectInvitationController.php:L26-L31`).

**Cross-Function Dependencies:** `AcceptProjectInvitation::handle`, route auth group, token hash/state/email
checks in `invitation-actions.md`.

**Open Questions:**
- Controller does not inspect token or invitation state; the Action is the sole visible state boundary.

---

## `ProjectInvitationController::store` in app/Http/Controllers/Collaboration/ProjectInvitationController.php (L33-L38)

**Purpose:** Creates a project invitation for a manager-authorized project and exposes the one-time plain token
to the redirect flash data.

**Inputs & Assumptions:**
- `InviteProjectMemberRequest`: `manageMembers` authorization and email/role validation
  (`InviteProjectMemberRequest.php:L11-L17`).
- `$project`: custom-bound accessible project; `$invite`: internal Action.

**Outputs & Effects:**
- Calls `InviteProjectMember::handle`, redirects to team page, and attaches `invitation_token` flash value
  (`ProjectInvitationController.php:L33-L38`). No direct database write in controller.

**Cross-Function Dependencies:** `InviteProjectMember::handle`, `ProjectRole::from`, team view route.

**Open Questions:**
- The plain token is intentionally passed through redirect flash; no other controller-side sink is visible in
  this method.

---

## `ProjectInvitationController::revoke` and `resend` in app/Http/Controllers/Collaboration/ProjectInvitationController.php (L40-L53)

**Purpose:** Delegate invitation revocation/resend for a route-bound invitation.

**Inputs & Assumptions:**
- `$invitation`: ordinary model-bound invitation; project authorization is delegated to the Actions.
- `$request`: authenticated user.
- `$revoke`/`$resend`: internal Actions.

**Outputs & Effects:**
- `revoke` calls the revocation Action and redirects; `resend` calls the resend Action and flashes the new plain
  token (`ProjectInvitationController.php:L40-L53`).

**Cross-Function Dependencies:** `RevokeProjectInvitation::handle`, `ResendProjectInvitation::handle`;
both re-authorize through `invitation->project`.

**Open Questions:**
- Controller does not compare invitation project to a route project because these routes have no `{project}`
  segment (`routes/web.php:L68-L69`); Action authorization is the visible project boundary.

---

## `ProjectMemberController::destroy` and `update` in app/Http/Controllers/Collaboration/ProjectMemberController.php (L16-L30)

**Purpose:** Enforce nested project/membership identity before delegating member removal or role change.

**Inputs & Assumptions:**
- `$project`: custom-bound accessible project.
- `$membership`: ordinary model-bound membership.
- `$request`: authenticated actor.
- FormRequest/Action provides ability checks.

**Outputs & Effects:**
- Both methods return 404 when membership `project_id` differs from route project; then delegate and redirect
  (`ProjectMemberController.php:L16-L30`).

**Block-by-Block:**

```php
// L16-L20 / L25-L27
abort_unless((int) $membership->project_id === (int) $project->getKey(), 404);
```
- **What:** Reasserts the nested relation.
- **Why here:** Route has both project and membership identifiers but default membership binding is not nested
  in the visible route declaration (`routes/web.php:L71-L72`).
- **Assumes:** integer keys represent membership/project relation.
- **Establishes:** membership belongs to route project before Action call.

```php
// L20-L30
$remove->handle(...); $change->handle(...);
```
- **What:** Delegates to Actions after the check.
- **Why here:** Actions own role/manager authorization and locked writes.
- **Assumes:** FormRequest authorization sees the route project before controller invocation.
- **Establishes:** redirect after successful domain operation.

**Cross-Function Dependencies:** `ChangeProjectMemberRoleRequest`, `RemoveProjectMember`,
`ChangeProjectMemberRole`, `ProjectPolicy::manageMembers/manageRoles`.

**Open Questions:**
- The route/FormRequest/implicit-binding order is framework-managed; application code shows both request
  authorization and controller relation check, but not the binding execution trace.
