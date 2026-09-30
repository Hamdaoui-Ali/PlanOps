# Notification delivery job

## `DeliverNotificationOutcome::handle` in `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php` (L47-L90)

### Purpose

Re-evaluates a queued notification outcome against current recipient and target state, persists or redacts notification state, and sends email at most once per idempotency key (L47-L90).

### Inputs & Assumptions

- The job carries a `NotificationOutcome`; the handler receives `PersistNotificationOutcome` (L47-L48).
- Recipient state is reloaded with a row lock before each delivery decision (L49-L52, L68-L71).
- `outcomeForDelivery` is the source of current recipient/target eligibility (L51-L52, L70-L73).

### Outputs & Effects

- Redacts an existing notification and stops when the recipient or target is no longer deliverable (L53-L57, L72-L77).
- Persists a current outcome before attempting email delivery (L59-L61).
- Locks the idempotent notification row, skips an already sent email, otherwise notifies the recipient and stores `email_sent_at` (L79-L90).

### Block-by-Block

#### First state evaluation and persistence — L49-L65

- What: Locks the recipient, resolves a current outcome, redacts when null, otherwise persists the outcome; then returns if there is no target (L49-L65).
- Why: Keeps notification persistence aligned with current recipient/target state before delivery (L49-L65).
- Assumes: A null outcome means the queued event should not remain actionable (L53-L57).
- Establishes: A persisted current outcome is required before email work (L59-L65).
- Depended on by: Second state evaluation and email idempotency (L68-L90).

#### Second state evaluation and idempotent send — L68-L90

- What: Re-locks the recipient, re-evaluates the outcome, locks the notification by idempotency key, skips if `email_sent_at` exists, otherwise sends and marks the timestamp (L68-L90).
- Why: Rechecks state across the persistence/send boundary and prevents duplicate email for the same idempotency key (L68-L90).
- Assumes: `PlanOpsNotification.email_sent_at` is the durable sent marker (L79-L90).
- Establishes: The email send attempt and sent timestamp for a deliverable outcome (L79-L90).
- Depended on by: Notification tests and mail delivery (`tests/Feature/Notifications/AfterCommitNotificationTest.php:L22-L123`, `tests/Feature/Notifications/NotificationDeliveryTest.php:L142-L258`).

### Cross-Function Dependencies

- Calls `outcomeForDelivery`, `PersistNotificationOutcome::handle`, and `PersistNotificationOutcome::redactExisting` (L51-L61, L70-L77).
- Uses `PlanOpsNotification`, `PlanOpsNotificationMail`, and the recipient’s `notify` method (L79-L90).

### Open Questions

- The handler’s first and second transactions both re-evaluate the recipient and target, while `PersistNotificationOutcome` establishes target-field redaction for targetless outcomes (`app/Domain/Notifications/Actions/PersistNotificationOutcome.php:L11-L32`) (L53-L61, L72-L77).

## `DeliverNotificationOutcome::outcomeForDelivery` in `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php` (L92-L102)

### Purpose

Selects the event-specific delivery resolver after rejecting absent or deactivated recipients (L92-L102).

### Inputs & Assumptions

- The recipient may be null or have `deactivated_at` set (L92-L94).
- Supported event types are invitation creation and assignee change (L96-L99).

### Outputs & Effects

- Returns null for absent/deactivated recipients, otherwise delegates to the invitation or assignment resolver (L93-L100).

### Block-by-Block

#### Recipient activity gate — L93-L94

- What: Returns null when the recipient is missing or deactivated (L93-L94).
- Why: Establishes current account activity before event-specific target checks (L93-L94).
- Assumes: A non-null `deactivated_at` is the inactive state (L93-L94; `app/Models/User.php:L35-L41, L84-L86`).
- Establishes: Only active, existing users reach target resolution (L93-L94).
- Depended on by: Invitation and assignment outcomes (L96-L100).

#### Event dispatch — L96-L100

- What: Dispatches to `invitationOutcome` or `assignmentOutcome` based on event type (L96-L100).
- Why: Applies event-specific identity and current-state rules (L96-L100).
- Assumes: The enum match covers all queued outcome types (L96-L100).
- Establishes: A nullable current outcome for the handler (L51-L57).
- Depended on by: `handle` (L51-L57, L70-L77).

### Cross-Function Dependencies

- Calls `invitationOutcome` and `assignmentOutcome` (L96-L100).
- Uses the `User` deactivation field and `NotificationEventType` enum (L93-L99).

### Open Questions

- This function gates delivery-time activity, while invitation creation/resend lookup may enqueue outcomes for any matching user (L93-L94; `app/Domain/Collaboration/Actions/InviteProjectMember.php:L62-L78`, `ResendProjectInvitation.php:L31-L47`).

## `DeliverNotificationOutcome::invitationOutcome` in `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php` (L104-L116)

### Purpose

Revalidates invitation identity, project identity, recipient email, pending state, expiry, and row lock before retaining an invitation outcome (L104-L116).

### Inputs & Assumptions

- The queued outcome supplies invitation target ID and project ID (L106-L108).
- Recipient email is compared case-insensitively to `normalized_email` (L108-L108).
- Pending state and expiry must both hold (L108-L110).

### Outputs & Effects

- Returns the original outcome when the invitation matches; otherwise returns an outcome with target removed (L113-L116).
- Locks the matching invitation while evaluating current state (L110-L110).

### Block-by-Block

#### Invitation revalidation — L106-L116

- What: Queries by invitation key, project key, normalized recipient email, null accepted/revoked fields, future expiry, and row lock (L106-L110), then chooses the original or targetless outcome (L113-L116).
- Why: Keeps delivery tied to a currently pending invitation for the intended project and email (L106-L116).
- Assumes: `withoutTarget()` is the representation for a notification that can persist but no longer carries an actionable target (L113-L116).
- Establishes: Invitation-target eligibility at delivery time (L106-L116).
- Depended on by: `handle` (L51-L57, L70-L77).

### Cross-Function Dependencies

- Uses `ProjectInvitation` and `NotificationOutcome` (L106-L116).

### Open Questions

- The query uses invitation ID and project ID from the outcome; nothing found in this method maps those identifiers back to the original route token (L106-L110).

## `DeliverNotificationOutcome::assignmentOutcome` in `app/Domain/Notifications/Jobs/DeliverNotificationOutcome.php` (L118-L147)

### Purpose

Revalidates an assignment notification against task accessibility, project identity, target task ID, active membership/ownership, and current assignee identity (L118-L147).

### Inputs & Assumptions

- The queued outcome supplies project and task target IDs (L120-L122).
- `Task::accessibleBy($recipient)` is the task visibility boundary used for delivery (L120-L126).
- The recipient must have an active project membership or be recognized as a project owner (L128-L142).

### Outputs & Effects

- Returns null when no active membership and no owner identity exist (L138-L140).
- Returns the original outcome only when the task remains assigned to the recipient; otherwise returns targetless outcome (L142-L145).
- Locks the task and active membership rows during the decision (L120-L136).

### Block-by-Block

#### Task and membership revalidation — L120-L136

- What: Loads a task through `accessibleBy`, project/target constraints, project relation, and row lock; separately locks an active membership (L120-L136).
- Why: Establishes current task visibility and project participation before delivery (L120-L136).
- Assumes: `Task::accessibleBy` and `removed_at is null` represent current task/project access (L120-L136; `app/Domain/Tasks/Models/Task.php:L115-L124`).
- Establishes: The task and active membership state available to owner/assignee checks (L120-L136).
- Depended on by: Owner and assignee decision (L138-L145).

#### Owner and assignee decision — L138-L145

- What: Computes owner identity from project `owner_id` or legacy `user_id`, requires active membership or owner status, then checks `task.assignee_id` (L138-L145).
- Why: Retains the target only when the recipient is still an eligible assignee (L138-L145).
- Assumes: Either project owner identity or active membership is sufficient for the delivery check (L138-L140).
- Establishes: Original vs targetless assignment outcome (L142-L145).
- Depended on by: `handle` notification persistence/send branches (L51-L57, L70-L77).

### Cross-Function Dependencies

- Uses `Task::accessibleBy`, `ProjectMembership`, project owner fields, and `NotificationOutcome::withoutTarget` (L120-L145).

### Open Questions

- The task access scope and the owner fallback both participate in this decision; nothing found in this function states whether the legacy owner identity is expected to remain populated for all migrated projects (L138-L140; `app/Domain/Projects/Models/Project.php:L92-L104`).
