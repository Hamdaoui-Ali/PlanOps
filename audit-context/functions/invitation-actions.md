# Invitation and membership actions

## `InviteProjectMember::handle` in `app/Domain/Collaboration/Actions/InviteProjectMember.php` (L22-L81)

### Purpose

Coordinates an invitation for a project member: authorizes the actor, normalizes and validates the email and role, records the invitation, emits a project event, and schedules an invitation notification when a matching user exists (L22-L81).

### Inputs & Assumptions

- The caller supplies an actor, project, email, and `ProjectRole` (L22-L22).
- `manageMembers` authorization and the role restriction are established before the transaction (L23-L31).
- The transaction re-locks the project, performs a case-insensitive recipient lookup, and rejects a matching deactivated account (L34-L41).
- Existing active membership and existing pending invitation are checked using normalized email (L43-L47).

### Outputs & Effects

- Creates an invitation with normalized email, role, inviter, SHA-256 token hash, seven-day expiry, and send timestamp; the plain token is attached as a runtime `plain_token` attribute (L50-L56).
- Creates an `INVITATION_CREATED` project event containing normalized email and role metadata (L57-L57).
- Builds and dispatches `NotificationOutcome` after the transaction when a matching recipient is present (L62-L78).
- Returns the created invitation (L81-L81).

### Block-by-Block

#### Authorization and input normalization — L23-L31

- What: Authorizes `manageMembers`, lowercases and trims the email, validates its shape, and restricts owner/admin invitation roles (L23-L31).
- Why: Establishes the actor and role conditions used by later writes (L23-L31).
- Assumes: The `ProjectPolicy` mapping and current project role are available to `Gate` (L23-L31; `app/Providers/AppServiceProvider.php:L27-L32`).
- Establishes: A normalized email and an allowed invitation role before transaction work begins (L25-L31).
- Depended on by: Recipient lookup and duplicate checks (L36-L47), invitation creation (L50-L56).

#### Locked project and recipient checks — L34-L47

- What: Locks the project, looks up a recipient by lowercased email, rejects deactivated recipients, and checks active membership and pending invitations (L34-L47).
- Why: Makes project and duplicate checks occur inside one database transaction (L34-L47).
- Assumes: `isActive()` is the account-state predicate (L40-L41; `app/Models/User.php:L84-L86`).
- Establishes: The recipient is either absent or active, and the normalized email is not already covered by an active membership or pending invitation (L40-L47).
- Depended on by: Invitation creation and notification recipient lookup (L50-L56, L62-L63).

#### Token, invitation, event, and notification — L50-L81

- What: Generates a random plain token, persists only its SHA-256 hash in the invitation row, attaches the plain value to the in-memory model, writes the event, then dispatches a notification outcome (L50-L78).
- Why: Connects the invitation row, project event, and recipient notification to the same invitation identity (L50-L78).
- Assumes: `plain_token` is consumed by the controller or mail path without being a persisted column (L55-L56; `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L33-L38`).
- Establishes: The returned invitation is the object used by the caller after commit (L50-L56, L81-L81).
- Depended on by: Invitation response/flash handling and notification delivery (L62-L78; `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php:L47-L147`).

### Cross-Function Dependencies

- Uses `ProjectPolicy::manageMembers` through `Gate` (L23-L23; `app/Policies/ProjectPolicy.php:L66-L69`).
- Uses `ProjectMembership`, `ProjectInvitation`, `ProjectEvent`, `NotificationOutcome`, and `DeliverNotificationOutcome` (L34-L78).
- The invitation controller flashes the runtime token after this action returns (L81-L81; `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L33-L38`).

### Open Questions

- `ProjectInvitationController::store` receives the action result and flashes `plain_token`; no separate record in this action establishes how long that value remains available outside the response path (L81-L81; `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L33-L38`).
- The action rejects a deactivated matching recipient, while the route-level handling of a deactivated authenticated actor is established by `EnsureUserIsActive::handle` (L40-L41; `app/Http/Middleware/EnsureUserIsActive.php:L12-L27`) and its registration remains to be traced in the empty application middleware configuration (`bootstrap/app.php:L13-L15`).

## `AcceptProjectInvitation::handle` in `app/Domain/Collaboration/Actions/AcceptProjectInvitation.php` (L15-L29)

### Purpose

Accepts an invitation addressed by a raw token by hashing the token, locking the invitation, requiring pending state, and delegating the membership write to `acceptLockedInvitation` (L15-L29).

### Inputs & Assumptions

- The caller supplies the authenticated user and raw token (L15-L16).
- An empty raw token is invalid before database work (L17-L19).
- Invitation identity is the SHA-256 hash of the supplied token (L21-L22).
- `isPending()` defines the usable invitation state (L22-L24).

### Outputs & Effects

- Returns the membership produced by `acceptLockedInvitation` inside a transaction (L21-L29).
- The invitation row is locked before pending-state evaluation (L21-L24).

### Block-by-Block

#### Raw token validation — L17-L19

- What: Rejects an empty token with a validation error (L17-L19).
- Why: Prevents an empty input from entering token lookup (L17-L19).
- Assumes: Non-empty is a minimum input condition; token validity is checked later (L17-L24).
- Establishes: Only non-empty tokens reach the transactional lookup (L17-L21).
- Depended on by: Hash lookup and locked acceptance (L21-L29).

#### Hashed lookup and delegation — L21-L29

- What: Hashes the raw token, locks the matching invitation, requires pending state, and delegates to `acceptLockedInvitation` (L21-L29).
- Why: Couples invitation state validation with the membership transaction (L21-L29).
- Assumes: `token_hash` is the invitation lookup field and `isPending()` includes expiry/state rules (L21-L24).
- Establishes: The delegated function receives a locked, pending invitation (L21-L29).
- Depended on by: Membership restoration/creation and acceptance event emission (L43-L63).

### Cross-Function Dependencies

- Calls `ProjectInvitation::isPending` and `acceptLockedInvitation` (L22-L29).
- The shared locked helper also serves `handleInvitation` (L31-L41).

### Open Questions

- The exact pending-state conditions are encapsulated in `ProjectInvitation::isPending` (accepted/revoked/expired checks at `app/Domain/Collaboration/Models/ProjectInvitation.php:L60-L63`); this record does not establish whether all callers use the raw-token path or the notification invitation-ID path (L22-L24; `app/Http/Controllers/NotificationController.php:L65-L85`).

## `AcceptProjectInvitation::handleInvitation` in `app/Domain/Collaboration/Actions/AcceptProjectInvitation.php` (L31-L41)

### Purpose

Accepts an invitation already identified by model instance, re-fetching and locking it by primary key before delegating to the same locked helper (L31-L41).

### Inputs & Assumptions

- The caller supplies a user and an invitation model (L31-L32).
- The supplied model is not trusted as the final state source; the action re-queries by key and checks pending state (L33-L38).

### Outputs & Effects

- Returns the membership from the shared acceptance helper inside a transaction (L33-L41).

### Block-by-Block

#### Locked invitation reload — L33-L41

- What: Looks up the invitation by key with `lockForUpdate`, rejects a missing or non-pending row, and calls `acceptLockedInvitation` (L33-L41).
- Why: Ensures the notification-driven acceptance path observes current invitation state before writing membership (L33-L41).
- Assumes: The invitation key is the target identity supplied by the notification controller (L33-L34; `app/Http/Controllers/NotificationController.php:L65-L85`).
- Establishes: The shared helper receives a locked, pending invitation (L35-L41).
- Depended on by: Membership write and invitation acceptance event (L43-L63).

### Cross-Function Dependencies

- Called by `NotificationController::acceptInvitation` through the invitation action (L33-L41; `app/Http/Controllers/NotificationController.php:L65-L85`).
- Shares `acceptLockedInvitation` with raw-token acceptance (L43-L63).

### Open Questions

- The notification controller checks recipient and notification type before calling the action, while this function establishes invitation identity and state only (L33-L41; `app/Http/Controllers/NotificationController.php:L65-L85`).

## `AcceptProjectInvitation::acceptLockedInvitation` in `app/Domain/Collaboration/Actions/AcceptProjectInvitation.php` (L43-L63)

### Purpose

Completes invitation acceptance after a caller has locked and validated the invitation: checks user activity and email ownership, restores or creates membership, marks the invitation accepted, and records a project event (L43-L63).

### Inputs & Assumptions

- The invitation is locked and pending when entered from either public action (L21-L29, L33-L41).
- The user must be active and their normalized email must equal the invitation’s normalized email (L45-L50).
- A membership is identified by `project_id` and `user_id` (L53-L57).

### Outputs & Effects

- Rejects deactivated users before membership work (L45-L47).
- Restores an existing membership by clearing removal fields or creates a new membership with the invitation role (L53-L57).
- Marks the invitation accepted and creates an `INVITATION_ACCEPTED` event with the resulting role (L59-L60).
- Returns the existing or newly created membership (L63-L63).

### Block-by-Block

#### User state and email binding — L45-L50

- What: Requires `User::isActive()` and compares lowercased trimmed user email with `normalized_email` (L45-L50).
- Why: Establishes account state and invitation-recipient identity before membership mutation (L45-L50).
- Assumes: `User::deactivated_at === null` means active (L45-L47; `app/Models/User.php:L84-L86`).
- Establishes: Only an active user whose email matches can proceed (L45-L50).
- Depended on by: Membership update/create (L53-L57).

#### Membership and invitation state — L53-L63

- What: Locks an existing membership, restores it when present, otherwise creates one, marks the invitation accepted, and writes the acceptance event (L53-L60).
- Why: Makes re-entry through a removed membership and first-time membership use the same invitation role (L53-L60).
- Assumes: `removed_at` and `removed_by_user_id` represent membership removal state (L53-L57).
- Establishes: The returned membership role and the invitation accepted timestamp (L53-L63).
- Depended on by: Controller redirect/flash behavior and project event consumers (L63-L63; `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L26-L31`).

### Cross-Function Dependencies

- Uses `User::isActive`, `ProjectMembership`, `ProjectInvitation`, and `ProjectEvent` (L45-L60).
- Called by both token-based and notification-based acceptance (L21-L29, L33-L41).

### Open Questions

- This helper establishes active-user and email checks, but does not establish whether `EnsureUserIsActive` is registered for every invitation route (`app/Http/Middleware/EnsureUserIsActive.php:L12-L27`; `bootstrap/app.php:L13-L15`).

## `DeclineProjectInvitation::handle` in `app/Domain/Collaboration/Actions/DeclineProjectInvitation.php` (L14-L34)

### Purpose

Locks a supplied invitation, requires pending state and matching recipient email, then marks the invitation revoked and records a recipient-declined event (L14-L34).

### Inputs & Assumptions

- The caller supplies a user and invitation model (L14-L15).
- The invitation key is reloaded under a transaction lock (L16-L19).
- User email is compared to the invitation’s normalized email (L21-L22).

### Outputs & Effects

- Sets `revoked_at` and creates an `INVITATION_REVOKED` event with reason `recipient_declined` (L25-L32).
- Returns no value (L14-L15).

### Block-by-Block

#### State and recipient checks — L16-L22

- What: Reloads and locks the invitation, requires pending state, and compares normalized emails (L16-L22).
- Why: Establishes current invitation state and recipient identity before revocation (L16-L22).
- Assumes: `isPending()` is the invitation lifecycle predicate (L16-L19).
- Establishes: Only the addressed recipient can decline a pending invitation (L16-L22).
- Depended on by: Revocation write and event (L25-L32).

#### Revocation event — L25-L32

- What: Writes `revoked_at` and an event identifying the recipient as actor and subject (L25-L32).
- Why: Records the lifecycle transition and its reason (L25-L32).
- Assumes: `INVITATION_REVOKED` represents both administrative revoke and recipient decline, differentiated by metadata (L25-L32).
- Establishes: A durable declined-invitation event (L25-L32).
- Depended on by: Project event/activity consumers.

### Cross-Function Dependencies

- Uses `ProjectInvitation::isPending`, `ProjectEvent`, and `ProjectEventType` (L16-L32).
- Called by `ProjectInvitationController::decline` (L40-L43; `app/Http/Controllers/Collaboration/ProjectInvitationController.php`).

### Open Questions

- The action checks the recipient email but does not call `User::isActive()`; nothing found in this function establishes whether deactivated users may decline an invitation (L21-L22).

## `ChangeProjectMemberRole::handle` in `app/Domain/Collaboration/Actions/ChangeProjectMemberRole.php` (L16-L34)

### Purpose

Authorizes role management, disallows assigning `OWNER`, locks the membership, changes a non-owner active membership role, and records the old/new roles (L16-L34).

### Inputs & Assumptions

- The caller supplies actor, membership, and target role (L16-L17).
- `manageRoles` is authorized against the membership’s project (L18-L18).
- The owner role is permanent for this action and removed memberships cannot be changed (L19-L26).

### Outputs & Effects

- Updates the membership role and returns the locked membership (L28-L34).
- Creates a `MEMBER_ROLE_CHANGED` project event with old and new role values (L28-L30).

### Block-by-Block

#### Authorization and role gate — L18-L20

- What: Authorizes `manageRoles` and rejects `OWNER` as the requested role (L18-L20).
- Why: Establishes the policy and enum boundary before transactional mutation (L18-L20).
- Assumes: Project policy resolves role-management authority (L18-L18; `app/Policies/ProjectPolicy.php:L71-L74`).
- Establishes: The target role is non-owner and the actor is authorized (L18-L20).
- Depended on by: Membership lock and write (L23-L30).

#### Locked membership write — L23-L30

- What: Locks the membership, rejects removed or owner memberships, writes the new role, and records the transition (L23-L30).
- Why: Serializes role changes and preserves old/new role metadata (L23-L30).
- Assumes: `removed_at` identifies removed membership state (L24-L26).
- Establishes: The stored role and corresponding event metadata (L27-L30).
- Depended on by: Controller redirect and project event consumers (L16-L17, L28-L30; `app/Http/Controllers/Collaboration/ProjectMemberController.php:L22-L28`).

### Cross-Function Dependencies

- Uses `ProjectPolicy::manageRoles`, `ProjectMembership`, `ProjectRole`, and `ProjectEvent` (L18-L30).

### Open Questions

- The controller checks membership/project ID equality but ordinary route binding for `ProjectMembership` is not customized in `routes/web.php` (L22-L23; `routes/web.php:L44-L50`).

## `RemoveProjectMember::handle` in `app/Domain/Collaboration/Actions/RemoveProjectMember.php` (L19-L51)

### Purpose

Authorizes member management, locks tasks assigned to the subject and the membership, clears subject assignments, records task activity, and marks the membership removed (L19-L51).

### Inputs & Assumptions

- The caller supplies actor, project, and subject user (L19-L20).
- `manageMembers` is authorized against the project (L20-L20).
- Tasks are selected by project and `assignee_id`, ordered, and locked before direct assignment changes (L23-L28).
- The membership must be active and cannot be the owner (L29-L34).

### Outputs & Effects

- Directly sets each matching task’s `assignee_id` to `null` and records `ASSIGNEE_CHANGED` activity (L36-L46).
- Sets membership `removed_at` and `removed_by_user_id`, then records `MEMBER_REMOVED` (L48-L49).
- Returns no value (L19-L20).

### Block-by-Block

#### Task and membership locks — L23-L34

- What: Locks all project tasks assigned to the subject and locks the matching membership; rejects absent/removed membership and owner membership (L23-L34).
- Why: Establishes the set of task rows and membership row affected by removal (L23-L34).
- Assumes: Assignment ownership is represented by `tasks.assignee_id` and membership ownership by `role` (L23-L34).
- Establishes: A locked task set and eligible non-owner membership (L23-L34).
- Depended on by: Assignment clearing and membership removal (L36-L49).

#### Assignment clearing and activity — L36-L46

- What: Writes `assignee_id = null` for each locked task and records old/new assignee metadata with the actor (L36-L46).
- Why: Keeps the assignment transition and audit record in the same transaction (L36-L46).
- Assumes: `TaskActivityRecorder::record` requires a persisted task and records actor/task/project fields (L36-L46; `app/Domain/Activity/Services/TaskActivityRecorder.php:L18-L45`).
- Establishes: The direct assignment-write path used during member removal (L36-L46).
- Depended on by: Activity feed queries and member-removal completion (L36-L46).

#### Membership removal and event — L48-L49

- What: Stores removal timestamp/actor and writes the project event (L48-L49).
- Why: Completes the membership lifecycle transition after assignment cleanup (L48-L49).
- Assumes: `removed_at` is the active-membership boundary used by scopes (L48-L49; `app/Domain/Projects/Models/Project.php:L92-L104`).
- Establishes: The subject is no longer an active membership (L48-L49).
- Depended on by: Access scopes, policy role resolution, and project team queries.

### Cross-Function Dependencies

- Uses `ProjectPolicy::manageMembers`, `Task`, `ProjectMembership`, `TaskActivityRecorder`, and `ProjectEvent` (L20-L49).
- The direct `assignee_id` write is separate from `AssignTask::handle`, but both are mutation paths for task assignment (`app/Domain/Tasks/Actions/AssignTask.php:L39-L53`).

### Open Questions

- The task query is scoped by project and assignee but does not call `Task::accessibleBy`; the action’s authorization boundary is the project policy and membership checks (L23-L34).

## `RevokeProjectInvitation::handle` in `app/Domain/Collaboration/Actions/RevokeProjectInvitation.php` (L15-L26)

### Purpose

Authorizes project member management, locks an invitation, requires pending state, marks it revoked, and records the normalized recipient email in a project event (L15-L26).

### Inputs & Assumptions

- The actor and invitation model are supplied by the controller (L15-L16).
- The invitation’s project is used for `manageMembers` authorization (L17-L17).
- The invitation is reloaded and locked by key before state evaluation (L19-L21).

### Outputs & Effects

- Sets `revoked_at` and creates an `INVITATION_REVOKED` event containing normalized email metadata (L23-L24).
- Returns no value (L15-L16).

### Block-by-Block

#### Authorization and state — L17-L21

- What: Authorizes the project, locks the invitation, and rejects non-pending invitations (L17-L21).
- Why: Establishes actor authority and current lifecycle state before mutation (L17-L21).
- Assumes: The invitation’s project relation is loaded or loadable for policy evaluation (L17-L19).
- Establishes: A pending invitation row eligible for revocation (L19-L21).
- Depended on by: Revocation write and event (L23-L24).

#### Revocation event — L23-L24

- What: Writes `revoked_at` and creates the event (L23-L24).
- Why: Records the administrative invitation transition (L23-L24).
- Assumes: Normalized email is suitable event metadata (L23-L24).
- Establishes: Durable revocation state and event (L23-L24).
- Depended on by: Invitation list/status consumers.

### Cross-Function Dependencies

- Called by `ProjectInvitationController::revoke` (L40-L43; `app/Http/Controllers/Collaboration/ProjectInvitationController.php`).
- Uses `ProjectPolicy::manageMembers`, `ProjectInvitation`, and `ProjectEvent` (L17-L24).

### Open Questions

- Ordinary route binding for `ProjectInvitation` is not replaced by a project-scoped binding in `routes/web.php`; this action relies on policy authorization plus its own invitation lock (L17-L21; `routes/web.php:L44-L50`).

## `ResendProjectInvitation::handle` in `app/Domain/Collaboration/Actions/ResendProjectInvitation.php` (L17-L51)

### Purpose

Authorizes resending, locks and validates a pending invitation, rotates its token and expiry, records an event, and schedules a notification outcome for a matching user (L17-L51).

### Inputs & Assumptions

- The actor and invitation model are supplied by the controller (L17-L18).
- `manageMembers` is authorized against the invitation’s project (L19-L19).
- The invitation must remain pending under a row lock (L21-L24).

### Outputs & Effects

- Replaces the token hash, expiry, and send timestamp; attaches the new plain token as a runtime attribute (L26-L29).
- Creates an `INVITATION_RESENT` event with normalized email (L29-L29).
- Dispatches a notification outcome for a matching user during the transaction’s after-commit path (L31-L47).
- Returns the locked invitation model (L51-L51).

### Block-by-Block

#### Pending invitation and token rotation — L19-L29

- What: Authorizes, locks, requires pending state, generates a new token, stores its hash, updates expiry/send time, and attaches the plain token (L19-L29).
- Why: Establishes the new invitation credential while retaining the invitation identity (L19-L29).
- Assumes: Existing accepted/revoked invitations cannot be resent because `isPending()` rejects them (L21-L24).
- Establishes: The returned invitation carries the new runtime token and updated lifecycle timestamps (L26-L29, L51-L51).
- Depended on by: Controller flash handling and notification dispatch (L31-L47; `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L46-L53`).

#### Notification outcome — L31-L47

- What: Resolves a lowercased email recipient, creates an invitation outcome, and schedules delivery after commit when a recipient exists (L31-L47).
- Why: Couples the rotated invitation state to recipient notification delivery (L31-L47).
- Assumes: Notification delivery rechecks invitation state and recipient activity (L31-L47; `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php:L92-L116`).
- Establishes: A post-transaction notification attempt for the current invitation ID (L33-L47).
- Depended on by: `DeliverNotificationOutcome` and notification persistence.

### Cross-Function Dependencies

- Uses `ProjectPolicy::manageMembers`, `ProjectInvitation`, `ProjectEvent`, `NotificationOutcome`, and `DeliverNotificationOutcome` (L19-L47).
- The controller flashes the runtime token returned by this action (L51-L51; `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L46-L53`).

### Open Questions

- The recipient lookup used for dispatch does not itself check `isActive()`; the delivery job performs the active-recipient check later (L31-L47; `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php:L92-L102`).
