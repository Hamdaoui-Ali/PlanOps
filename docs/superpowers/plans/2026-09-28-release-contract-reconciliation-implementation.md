# Release Contract Reconciliation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reconcile active release, backlog, architecture, and UI documentation with the verified collaboration branch without overstating the remaining release gate.

**Architecture:** Keep the current implementation unchanged and update only the documentation contracts that describe it. Use short PowerShell assertions against exact repository strings before and after each document group, then finish with a full documentation and diff verification pass.

**Tech Stack:** Markdown, PowerShell, Git, Laravel/Playwright evidence already recorded in the repository.

**Spec:** `docs/superpowers/specs/2026-09-28-release-contract-reconciliation-design.md`

## Global Constraints

- `php artisan test --compact`: 336 passed, 3 environment-scoped skips, 1616 assertions.
- `npm.cmd run test:browser`: 5 passed.
- The three known skips are `AssignmentConcurrencyTest`, `TaskNumberConcurrencyTest`, and `TaskCreationConcurrencyTest`.
- Team Work is a project-scoped Owner/Admin workload view, not a productivity ranking.
- Role-change activity, member-removal notifications, team analytics, and realtime delivery remain deferred.
- No application code, route, policy, query, or visual redesign changes.
- No claim may imply that the PostgreSQL/concurrency release gate is complete.

## Review Focus

- A reader sees the current test counts and browser result rather than the stale 301/2/no-browser baseline; assert exact evidence in Task 1.
- A reader can distinguish browser coverage from the incomplete PostgreSQL/P0/P1 release gate; assert status and remaining checkboxes in Task 1.
- A reader does not mistake Team Work for team analytics or member ranking; assert the limited scope in Tasks 1 and 2.
- Route names in the UI contract match `routes/web.php`; assert both Team routes in Task 3.
- Historical single-user language remains labeled as historical/superseded rather than silently becoming current architecture; assert the overlay and labels in Task 3.

---

### Task 1: Refresh release evidence and backlog state

**Files:**
- Modify: `docs/backlogs/README.md`
- Modify: `docs/backlogs/DYX-007-release-verification.md`
- Test: temporary ignored assertion script under `.superpowers/sdd/` for the release/backlog contract

**Interfaces:**
- Consumes: `docs/reports/2026-09-28-planops-team-work-browser-verification.md` and the current branch verification results.
- Produces: backlog and DYX-007 language that records current evidence, marks only covered browser evidence as present, records the delivered Team Work slice, and keeps unresolved release criteria visible.

- [ ] **Step 1: Write the failing release/backlog assertions**

  Assert that the files contain `336 passed`, `5 passed`, `npm.cmd run test:browser`,
  the three known skip names, and the delivered Team Work evidence link. Also assert
  that the stale `301 passed`, `2 environment-scoped skips`, and `No browser/axe
  suite is configured` claims are absent. Run the assertions before editing.

- [ ] **Step 2: Run the assertions and verify the expected failure**

  Run the temporary PowerShell assertion script. Expected: FAIL because the current
  backlog and DYX-007 documents still contain the old evidence and deferred wording.

- [ ] **Step 3: Update the backlog index**

  Replace the stale current gate with the verified application/browser evidence;
  change the P2 boundary to identify the delivered initial Team Work slice while
  keeping team analytics, additional notifications, and realtime deferred; mark the
  two Team Work information-architecture/visibility bullets complete; and replace
  the stale next action with the current release-contract reconciliation state.

- [ ] **Step 4: Update DYX-007 release verification**

  Change the status to verification-in-progress rather than approved; link the
  dated evidence report; record the exact commands/results and known skips; update
  the browser task to acknowledge the configured Playwright/axe harness while
  limiting its coverage claim to the public and Team Work journeys; add
  `npm.cmd run test:browser` to verification commands; and leave PostgreSQL,
  concurrency, and broader P0/P1 acceptance criteria unchecked.

- [ ] **Step 5: Run the release/backlog assertions again**

  Run the same script. Expected: PASS with no stale release evidence claims.

- [ ] **Step 6: Commit**

  ```bash
  git add docs/backlogs/README.md docs/backlogs/DYX-007-release-verification.md
  git diff --cached --check
  git commit -m "docs: refresh release evidence and backlog state"
  ```

### Task 2: Reconcile the Sprint 2 authority checklist

**Files:**
- Modify: `docs/PlanOps_Sprint_2.md`
- Test: PowerShell assertions for the P2 checklist and release-boundary note

**Interfaces:**
- Consumes: the delivered Team Work route/report and the Sprint 2 P2 rules in the approved spec.
- Produces: an authority checklist that records the limited Team Work delivery without marking unrelated P2 features complete.

- [ ] **Step 1: Write the failing authority assertions**

  Assert that the P2 checklist contains a checked Team Work entry with the
  Owner/Admin project-scoped workload limitation, and a note that role-change
  activity, removal notification, team analytics, and realtime delivery remain
  deferred. Run before editing.

- [ ] **Step 2: Run the assertions and verify the expected failure**

  Expected: FAIL because the authority checklist still shows `Team Work screen`
  unchecked and does not record the delivered slice.

- [ ] **Step 3: Update the P2 checklist and boundary note**

  Mark only the delivered initial Team Work screen as complete, link the design and
  verification evidence, describe its Owner/Admin/no-ranking boundary, and keep the
  remaining P2 items unchecked with the existing release-gate rule intact.

- [ ] **Step 4: Run the authority assertions**

  Expected: PASS, with role-change, removal notification, team analytics, and
  realtime still visibly deferred.

- [ ] **Step 5: Commit**

  ```bash
  git add docs/PlanOps_Sprint_2.md
  git diff --cached --check
  git commit -m "docs: reconcile Sprint 2 Team Work status"
  ```

### Task 3: Align architecture and UI contracts

**Files:**
- Modify: `docs/architecture/stack.md`
- Modify: `docs/ui/screen-spec.md`
- Test: PowerShell assertions for collaboration overlay, route names, and remaining P2 wording

**Interfaces:**
- Consumes: route names from `routes/web.php`, policy behavior from the Team Work tests, and the historical-baseline decisions in the approved spec.
- Produces: architecture and UI contracts that distinguish historical single-user boundaries from the current collaboration overlay.

- [ ] **Step 1: Write the failing architecture/UI assertions**

  Assert that the architecture document contains a current collaboration overlay;
  the UI document contains `GET /projects/{project}/team` and
  `GET /projects/{project}/team/work`; the Team Work contract states Owner/Admin,
  active-member scope, workload visibility, and no ranking; and the old blanket
  `P2 features are out of scope for all screens` sentence is absent. Run before
  editing.

- [ ] **Step 2: Run the assertions and verify the expected failure**

  Expected: FAIL because the current documents still expose only the historical
  route map and blanket P2 wording.

- [ ] **Step 3: Update the architecture contract**

  Retitle the old team/assignment boundary as historical context and add a current
  Sprint 2 collaboration overlay covering memberships, assignments, notifications,
  and Team Work; explicitly keep team analytics and realtime deferred.

- [ ] **Step 4: Update the UI contract**

  Add Team and Team Work routes and the manager-view behavior; replace owner-only
  authorization wording with policy and active-membership query-scope wording; add
  keyboard/table/accessibility expectations; and state that only remaining P2
  surfaces are out of scope.

- [ ] **Step 5: Run the architecture/UI assertions**

  Expected: PASS, with route names matching `routes/web.php` and no false claim that
  all P2 work is implemented.

- [ ] **Step 6: Commit**

  ```bash
  git add docs/architecture/stack.md docs/ui/screen-spec.md
  git diff --cached --check
  git commit -m "docs: align architecture and UI contracts"
  ```

### Task 4: Verify the reconciled documentation set

**Files:**
- Test: `docs/superpowers/specs/2026-09-28-release-contract-reconciliation-design.md`, all changed documents, and repository route/source files

**Interfaces:**
- Consumes: the three committed documentation groups and the existing evidence report.
- Produces: fresh verification evidence for the final handoff; no additional product behavior.

- [ ] **Step 1: Run the complete documentation assertion set**

  Check current evidence, known skips, report links, Team Work scope, route-name
  parity, historical labels, deferred P2 items, and absence of stale claims.

- [ ] **Step 2: Run repository hygiene checks**

  Run `git diff --check`, the documentation search/placeholder scan from DYX-007,
  and `git status --short --branch`. Expected: no whitespace errors, no stale
  release claims outside clearly labeled historical context, and only the intended
  commits in the branch.

- [ ] **Step 3: Commit any verification-only correction separately**

  If the checks identify a documentation-only mismatch, fix it with a focused
  commit named for the affected contract. Do not change application behavior.

- [ ] **Step 4: Record task completion**

  Run the full relevant verification commands and record their actual output in the
  SDD ledger before claiming completion.
