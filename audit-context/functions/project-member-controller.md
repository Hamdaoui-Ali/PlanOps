# Project member controller

## `ProjectMemberController::destroy` in `app/Http/Controllers/Collaboration/ProjectMemberController.php` (L16-L22)

### Purpose

Checks that the route-bound membership belongs to the route project, delegates removal to `RemoveProjectMember`, and redirects with a status message (L16-L22).

### Inputs & Assumptions

- Receives the authenticated request, project, membership, and removal action (L16-L17).
- The explicit project/membership key comparison is the controller’s local nesting check (L18-L18).
- The action receives the membership’s related user as the removal subject (L19-L19).

### Outputs & Effects

- Delegates task assignment clearing and membership removal to `RemoveProjectMember::handle` (L19-L19; `app/Domain/Collaboration/Actions/RemoveProjectMember.php:L19-L51`).
- Returns a back redirect with `Member removed.` (L21-L22).

### Block-by-Block

#### Nested membership check and delegation — L18-L22

- What: Aborts with 404 on project/membership mismatch, calls the removal action, then redirects (L18-L22).
- Why: Connects route nesting to the action’s policy and membership checks (L18-L22).
- Assumes: `membership->user` identifies the subject user for removal (L19-L19).
- Establishes: The project-member removal entry point and action handoff (L18-L22).
- Depended on by: `RemoveProjectMember::handle` (L19-L51).

### Cross-Function Dependencies

- Uses `RemoveProjectMember` and the request’s authenticated user (L16-L20).

### Open Questions

- Ordinary route binding for `ProjectMembership` is not replaced by the custom project/task bindings in `routes/web.php`; the controller’s explicit key check is the local relationship check (L18-L18; `routes/web.php:L44-L50`).

## `ProjectMemberController::update` in `app/Http/Controllers/Collaboration/ProjectMemberController.php` (L24-L30)

### Purpose

Checks project/membership nesting, obtains the validated role, delegates the role change, and redirects with a status message (L24-L30).

### Inputs & Assumptions

- Receives an authorized `ChangeProjectMemberRoleRequest`, project, membership, and role action (L24-L25).
- The request supplies a validated role string used to construct `ProjectRole` (L26-L27).
- The explicit project/membership key comparison guards the nested route relationship (L26-L26).

### Outputs & Effects

- Delegates role mutation to `ChangeProjectMemberRole::handle` (L27-L27; `app/Domain/Collaboration/Actions/ChangeProjectMemberRole.php:L16-L34`).
- Returns a back redirect with `Member role updated.` (L29-L30).

### Block-by-Block

#### Request, nesting, and role delegation — L26-L30

- What: Aborts on project mismatch, converts the validated role to enum, invokes the action, and redirects (L26-L30).
- Why: Keeps request validation, route nesting, and role mutation as separate boundaries (L24-L30).
- Assumes: `ChangeProjectMemberRoleRequest` establishes request authorization and role validation (L24-L27; `app/Http/Requests/Collaboration/ChangeProjectMemberRoleRequest.php:L11-L14`).
- Establishes: The role-change entry point and validated enum handoff (L26-L30).
- Depended on by: `ChangeProjectMemberRole::handle` (L27-L27).

### Cross-Function Dependencies

- Uses `ChangeProjectMemberRoleRequest`, `ProjectRole`, and `ChangeProjectMemberRole` (L24-L27).

### Open Questions

- The request authorizes the actor and the action authorizes again; this record does not establish whether both checks intentionally cover different state or are redundant (L24-L27; `app/Domain/Collaboration/Actions/ChangeProjectMemberRole.php:L18-L20`).
