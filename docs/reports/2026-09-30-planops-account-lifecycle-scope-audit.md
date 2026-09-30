# PlanOps account lifecycle and security-scope audit

**Date:** 2026-09-30

**Branch:** `feature/planops-browser-qa-team-work`

## Delivered

This slice closes the application-side account-lifecycle and invitation-surface gaps identified during the DYX-007.3 context review:

- Profile deactivation now retains the user row and history, revokes the current session, blocks stale authenticated sessions, and excludes deactivated credentials from login.
- Project, task, and label access scopes and policies now return no access for deactivated users.
- New assignments, invitations, and invitation acceptance reject deactivated collaboration targets or actors.
- Public invitation previews no longer expose project names or invitee email addresses.
- Public invitation preview, create, resend, and accept routes have a bounded `6,1` throttle.
- Expired invitations are excluded from the Team page's pending-invitation state.
- The context-only audit map is indexed in [`audit-context/DOSSIER.md`](../../audit-context/DOSSIER.md), including route bindings, access scopes, collaboration actions, notification delivery, activity recording, and mutation surfaces.

## Focused regression coverage

The changes are covered by tests for:

- deactivated login and stale-session revocation;
- retained user rows after self-deactivation;
- deactivated-user policy/scope denial;
- deactivated assignee, invitation recipient, and invitation acceptor rejection;
- generic public invitation preview privacy;
- invitation preview rate limiting; and
- expired invitations not appearing as pending team state.

## Verification

```text
php artisan test --no-ansi
```

Result: **365 passed, 3 environment-scoped skips, 1,735 assertions**.

The skips remain the existing PostgreSQL/independent-process concurrency cases:

- `tests/Feature/Collaboration/AssignmentConcurrencyTest.php`
- `tests/Feature/Tasks/TaskNumberConcurrencyTest.php`
- `tests/Feature/Collaboration/TaskCreationConcurrencyTest.php`

The suite also continues to emit existing non-compound `use` warnings in several test files; they do not fail the run.

```text
npm.cmd run test:browser
```

Result: **13 passed**. The command's `pretest:browser` hook rebuilt the Vite production assets before Playwright ran the public, collaboration, My Work, invitation, notification, keyboard, narrow-viewport, and axe journeys.

Additional checks passed:

- `php artisan view:cache`
- `php artisan route:list --path=invitations --no-ansi`
- `git diff --check`

## Release boundary

This is application-side evidence for DYX-007.3. It does not close PostgreSQL migration/rollback proof, the three environment-scoped concurrency skips, mobile-specific collaboration journeys, or the broader P0/P1 release decision. Those exceptions remain visible in [`DYX-007-release-verification.md`](../backlogs/DYX-007-release-verification.md).
