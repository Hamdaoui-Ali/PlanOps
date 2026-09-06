<?php

namespace App\Http\Controllers\Collaboration;

use App\Domain\Collaboration\Actions\AcceptProjectInvitation;
use App\Domain\Collaboration\Actions\InviteProjectMember;
use App\Domain\Collaboration\Actions\ResendProjectInvitation;
use App\Domain\Collaboration\Actions\RevokeProjectInvitation;
use App\Domain\Collaboration\Enums\ProjectRole;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Projects\Models\Project;
use App\Http\Requests\Collaboration\InviteProjectMemberRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectInvitationController
{
    public function show(string $token): View
    {
        $invitation = ProjectInvitation::query()->with('project')->where('token_hash', hash('sha256', $token))->first();

        return view('pages.invitations.show', ['invitation' => $invitation]);
    }

    public function accept(Request $request, string $token, AcceptProjectInvitation $accept): RedirectResponse
    {
        $accept->handle($request->user(), $token);

        return to_route('projects.index')->with('status', 'You joined the project.');
    }

    public function store(InviteProjectMemberRequest $request, Project $project, InviteProjectMember $invite): RedirectResponse
    {
        $invitation = $invite->handle($request->user(), $project, $request->validated('email'), ProjectRole::from($request->validated('role')));

        return to_route('projects.team', $project)->with('status', 'Invitation created.')->with('invitation_token', $invitation->plain_token);
    }

    public function revoke(Request $request, ProjectInvitation $invitation, RevokeProjectInvitation $revoke): RedirectResponse
    {
        $revoke->handle($request->user(), $invitation);

        return back()->with('status', 'Invitation revoked.');
    }

    public function resend(Request $request, ProjectInvitation $invitation, ResendProjectInvitation $resend): RedirectResponse
    {
        $invitation = $resend->handle($request->user(), $invitation);

        return back()->with('status', 'Invitation resent.')->with('invitation_token', $invitation->plain_token);
    }
}
