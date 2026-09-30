# Mutation entry-point map

## task and collaboration mutation entry points in `routes/web.php`, controllers, and actions

### Purpose

Maps the security-sensitive mutation routes to their controller handoffs and domain actions, with emphasis on status, reorder, assignment, project membership, invitation, and label writes (DYX-007.3 contract: `docs/backlogs/DYX-007-release-verification.md:L74-L86`).

### Inputs & Assumptions

- Route reachability starts with the authenticated route group and project/task custom bindings (L36-L50; `routes/web.php`).
- Controller request objects provide validation/authorization before action calls where shown in the route/controller chain (for example `app/Http/Controllers/TaskController.php:L55-L68`, `app/Http/Requests/ChangeTaskStatusRequest.php:L12-L23`).
- Domain actions re-load or lock target rows for mutation in the task and collaboration paths cited below.

### Outputs & Effects

- The map records the reachable chain for each mutation family; it does not assign a verdict to the chain.
- Direct task assignee writes are present in `AssignTask` and `RemoveProjectMember` (L39-L53; L36-L46 of the respective actions).

### Block-by-Block

#### Task status and board status — `routes/web.php:L78-L86`

- What: Project board status and task status routes reach `ProjectBoardController::changeStatus` and `TaskController::status` (L78-L86; `routes/web.php`).
- Why: Both routes expose status mutation entry points for a task.
- Assumes: The task/project route bindings and controller checks establish the project/task relationship (`routes/web.php:L44-L50`; `app/Http/Controllers/ProjectBoardController.php:L33-L43`).
- Establishes: Both controller paths call `ChangeTaskStatus` (`app/Http/Controllers/ProjectBoardController.php:L33-L43`, `app/Http/Controllers/TaskController.php:L55-L68`; `app/Domain/Tasks/Actions/ChangeTaskStatus.php:L17-L52`).
- Depended on by: Task status activity and project board/read surfaces.

#### Task reorder — `routes/web.php:L76-L80`

- What: The project board reorder route reaches `ProjectBoardController::reorder`, which delegates to `ReorderTasks` (L76-L80; `app/Http/Controllers/ProjectBoardController.php:L46-L58`; `app/Domain/Tasks/Actions/ReorderTasks.php:L17-L57`).
- Why: Establishes the bulk position-write path.
- Assumes: The action receives a project and requested task IDs/positions from the request (L46-L58; `app/Http/Requests/ReorderTasksRequest.php:L11-L14`).
- Establishes: Project access, task selection, and position writes are evaluated in `ReorderTasks` (L30-L52).
- Depended on by: Board ordering and subsequent project-board queries.

#### Task creation and detail mutation — `routes/web.php:L83-L93`

- What: Task creation reaches `TaskController::create/store`; update, detail update, priority, due-date, assignment, and delete routes reach their named controller methods (L83-L93; `routes/web.php`).
- Why: Provides the route-to-action inventory for task mutation.
- Assumes: Request classes and controllers are the entry boundaries for each action.
- Establishes: The following action chain: `CreateTask` (`app/Http/Controllers/TaskController.php:L175-L209`; `app/Domain/Tasks/Actions/CreateTask.php:L22-L111`), `UpdateTask` (`app/Http/Controllers/TaskController.php:L70-L99`; `app/Domain/Tasks/Actions/UpdateTask.php:L15-L76`), `UpdateTaskDetails` (`app/Http/Controllers/TaskController.php:L77-L82`; `app/Domain/Tasks/Actions/UpdateTaskDetails.php:L18-L130`), `ChangeTaskPriority` (`app/Http/Controllers/TaskController.php:L84-L97`; `app/Domain/Tasks/Actions/ChangeTaskPriority.php:L17-L54`), `ChangeTaskDueDate` (`app/Http/Controllers/TaskController.php:L160-L165`; `app/Domain/Tasks/Actions/ChangeTaskDueDate.php:L16-L55`), `AssignTask` (`app/Http/Controllers/TaskController.php:L35-L40`; `app/Domain/Tasks/Actions/AssignTask.php:L18-L75`), and `DeleteTask` (`app/Http/Controllers/TaskController.php:L167-L173`; `app/Domain/Tasks/Actions/DeleteTask.php:L14-L43`).
- Depended on by: Task detail, board, dashboard, activity, analytics, search, and export surfaces.

#### Project membership and invitation mutations — `routes/web.php:L52-L75`

- What: Project collaboration routes reach member, invitation, and team controllers (L52-L75; `routes/web.php`).
- Why: Establishes the route family for membership and invitation writes.
- Assumes: Controllers perform explicit project/nested-model checks where present (`app/Http/Controllers/Collaboration/ProjectMemberController.php:L16-L30`, `app/Http/Controllers/Collaboration/ProjectInvitationController.php:L19-L53`).
- Establishes: Domain action paths for invite/accept/decline/revoke/resend and member role/remove operations (`app/Domain/Collaboration/Actions/InviteProjectMember.php:L22-L81`, `AcceptProjectInvitation.php:L15-L63`, `DeclineProjectInvitation.php:L14-L34`, `RevokeProjectInvitation.php:L15-L26`, `ResendProjectInvitation.php:L17-L51`, `ChangeProjectMemberRole.php:L16-L34`, `RemoveProjectMember.php:L19-L51`).
- Depended on by: Project access scopes, team views, notification outcomes, and project events.

#### Label mutations — `routes/web.php:L87-L93` and label actions

- What: The task mutation area includes label attach/detach/delete action paths, with each action applying its own policy and accessible task/project lookups (`app/Domain/Labels/Actions/AttachLabelToTask.php:L15-L44`, `DetachLabelFromTask.php:L15-L44`, `DeleteLabel.php:L15-L45`).
- Why: Labels affect task/project-visible state and are part of search/filter query paths.
- Assumes: Label policy and accessible scopes are the action boundaries (the cited action lines).
- Establishes: Separate label mutation handlers rather than direct controller writes (the cited action lines).
- Depended on by: `SearchQueryService`, project task list filters, and task detail label relations.

#### Direct assignee-write inventory — action paths

- What: `AssignTask` writes a supplied assignee after policy, transaction, membership, and project-owner checks; `RemoveProjectMember` clears matching task assignees during member removal (L39-L53; `app/Domain/Collaboration/Actions/RemoveProjectMember.php:L36-L46`).
- Why: Captures all direct `assignee_id` mutation paths identified in the scoped action inventory.
- Assumes: Other assignment callers use `AssignTask` rather than writing the column directly; nothing found in the scoped direct-write inventory establishes another caller.
- Establishes: The two recorded assignee-write call sites and their activity recording handoffs (L39-L53; `RemoveProjectMember.php:L36-L46`).
- Depended on by: Dashboard/My Work/analytics/activity/export scopes and notification assignment delivery.

### Cross-Function Dependencies

- Route bindings feed controller parameters (`routes/web.php:L44-L50`).
- Controllers feed request validation and domain actions; actions write task, membership, invitation, label, event, and activity state.
- Read/query surfaces listed in `audit-context/functions/` consume the resulting state through access scopes and policy checks.

### Open Questions

- The task-controller line reference for priority/due-date methods should be confirmed against the current file because the route/action chain is captured here from the route inventory and action records (`routes/web.php:L89-L90`; `app/Domain/Tasks/Actions/ChangeTaskPriority.php:L17-L54`, `ChangeTaskDueDate.php:L16-L55`).
- The label action routes are not separately listed in the current `routes/web.php` excerpt; the action records establish their policy/scope behavior, while route reachability remains to be confirmed in the full route file.
