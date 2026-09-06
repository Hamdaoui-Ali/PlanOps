<?php

namespace App\Http\Controllers\Collaboration;

use App\Domain\Collaboration\Actions\ChangeProjectMemberRole;
use App\Domain\Collaboration\Actions\RemoveProjectMember;
use App\Domain\Collaboration\Actions\TransferProjectOwnership;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Projects\Models\Project;
use App\Http\Requests\Collaboration\ChangeProjectMemberRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectMemberController
{
    public function destroy(Request $request, Project $project, ProjectMembership $membership, RemoveProjectMember $remove): RedirectResponse
    {
        abort_unless((int) $membership->project_id === (int) $project->getKey(), 404);
        $remove->handle($request->user(), $project, $membership->user);

        return back()->with('status', 'Member removed.');
    }

    public function update(ChangeProjectMemberRoleRequest $request, Project $project, ProjectMembership $membership, ChangeProjectMemberRole $change): RedirectResponse
    {
        abort_unless((int) $membership->project_id === (int) $project->getKey(), 404);
        $change->handle($request->user(), $membership, ProjectRole::from($request->validated('role')));

        return back()->with('status', 'Member role updated.');
    }

    public function transfer(Request $request, Project $project, ProjectMembership $membership, TransferProjectOwnership $transfer): RedirectResponse
    {
        abort_unless((int) $membership->project_id === (int) $project->getKey(), 404);
        $transfer->handle($request->user(), $project, $membership->user);

        return back()->with('status', 'Project ownership transferred.');
    }
}
