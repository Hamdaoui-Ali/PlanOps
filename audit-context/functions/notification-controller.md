## `NotificationController::index` in app/Http/Controllers/NotificationController.php (L28-L47)

**Purpose:** Lists recipient-owned notifications and loads invitation records referenced by invitation-created
notifications for the authenticated user's email.

**Inputs & Assumptions:**
- `$request->user()` (`User`): authenticated recipient. Trust: trusted controller subject.
- `$notifications`: `NotificationCenterQuery`.
- Implicit: notification target IDs are invitation IDs; invitation normalized email is compared to current
  recipient email (`NotificationController.php:L32-L47`).

**Outputs & Effects:**
- Reads/paginates notifications, loads matching invitations, returns notification view and unread count
  (`NotificationController.php:L28-L47`). No writes.

**Block-by-Block:**

```php
// L30-L34
$query = $notifications->for($recipient); $page = $query->paginate(30)->withQueryString();
```
- **What:** Establishes recipient-scoped notification query.
- **Why here:** All later notification IDs come from the recipient's page.
- **Assumes:** `NotificationCenterQuery::for` scopes `recipient_id`.
- **Establishes:** visible notification collection.

```php
// L35-L47
$invitationIds = ...; $invitations = ProjectInvitation::query()->with('invitedBy')->whereIn('id', ...)->whereRaw(...)->get();
```
- **What:** Selects invitation targets and additionally matches recipient email.
- **Why here:** The view needs invitation metadata only for notifications the recipient owns.
- **Assumes:** `target_type` filtering at `L35-L37` is sufficient with event type and target ID; email is
  normalized consistently with invitation creation.
- **Establishes:** invitation map keyed by notification target ID.

**Cross-Function Dependencies:** `NotificationCenterQuery::for`, `PlanOpsNotification::forRecipient`,
`ProjectInvitation` normalized email.

**Open Questions:**
- The invitation query constrains IDs and email but does not repeat a target/project relationship check beyond
  those fields (`NotificationController.php:L36-L41`).

---

## `NotificationController::read` and `readAll` in app/Http/Controllers/NotificationController.php (L50-L63)

**Purpose:** Mark one or all notifications as read for the current recipient.

**Inputs & Assumptions:**
- `$request->user()` (`User`): authenticated recipient.
- `$notification`: ordinary model-bound notification for `read`; controller re-queries by recipient.

**Outputs & Effects:**
- `read` loads recipient-owned notification, writes `read_at` if empty, and redirects
  (`NotificationController.php:L50-L56`). `readAll` updates all unread recipient rows (`L58-L63`).

**Cross-Function Dependencies:** `PlanOpsNotification::scopeForRecipient` (`PlanOpsNotification.php:L36-L39`).

**Open Questions:**
- `readAll` uses a bulk update and does not load individual notification models; no event/audit callback is
  visible in this path (`NotificationController.php:L58-L63`).

---

## `NotificationController::acceptInvitation` and `declineInvitation` in app/Http/Controllers/NotificationController.php (L65-L85)

**Purpose:** Convert a recipient-owned invitation notification into an accept/decline domain operation.

**Inputs & Assumptions:**
- `$notification`: ordinary model-bound notification; controller re-queries it through recipient scope.
- `$request->user()`: authenticated recipient.
- `$accept`/`$decline`: domain Actions.
- Implicit: event type `INVITATION_CREATED`, target type `project_invitation`, and target ID form a valid
  invitation reference (`NotificationController.php:L67-L74`, `L79-L84`).

**Outputs & Effects:**
- Both re-load recipient-owned notification, abort 404 for non-invitation metadata, load target invitation by
  ID, delegate to Action, mark notification read, and redirect (`NotificationController.php:L65-L85`).

**Block-by-Block:**

```php
// L67-L74 / L79-L84
$owned = PlanOpsNotification::query()->forRecipient(...)->whereKey(...)->firstOrFail();
abort_unless($owned->event_type === ... && $owned->target_type === ..., 404);
$invitation = ProjectInvitation::query()->whereKey($owned->target_id)->firstOrFail();
```
- **What:** Establishes recipient ownership and target type before loading invitation.
- **Why here:** Model binding alone is not the recipient boundary.
- **Assumes:** notification target ID was persisted consistently with its target type.
- **Establishes:** invitation model passed to the domain Action.

```php
// L75-L76 / L84-L85
$accept->handleInvitation(...); $owned->forceFill(['read_at' => ...])->save();
```
- **What:** Delegates state validation/email/membership operation, then marks notification read.
- **Why here:** Read marker follows successful domain operation.
- **Assumes:** the Action's transaction/state checks are authoritative for invitation validity.
- **Establishes:** notification read state after returned Action.

**Cross-Function Dependencies:** `PlanOpsNotification::forRecipient`, `AcceptProjectInvitation::handleInvitation`,
`DeclineProjectInvitation::handle`.

**Open Questions:**
- Target invitation lookup is by ID only in the controller; recipient-email and pending checks are delegated to
  Actions and notification delivery, so their combined contract must be read as one path.
