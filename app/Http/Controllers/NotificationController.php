<?php

namespace App\Http\Controllers;

use App\Domain\Collaboration\Actions\AcceptProjectInvitation;
use App\Domain\Collaboration\Actions\DeclineProjectInvitation;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Notifications\Enums\NotificationEventType;
use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Domain\Notifications\Queries\NotificationCenterQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificationController extends Controller
{
    public function index(Request $request, NotificationCenterQuery $notifications): View
    {
        $recipient = $request->user();
        $query = $notifications->for($recipient);
        $page = $query->paginate(30)->withQueryString();
        $invitationIds = $page->getCollection()
            ->filter(fn (PlanOpsNotification $notification): bool => $notification->event_type === NotificationEventType::INVITATION_CREATED)
            ->pluck('target_id')->filter()->all();
        $invitations = ProjectInvitation::query()
            ->with('invitedBy')
            ->whereIn('id', $invitationIds)
            ->whereRaw('LOWER(normalized_email) = ?', [strtolower($recipient->email)])
            ->get()
            ->keyBy('id');

        return view('pages.notifications.index', [
            'notifications' => $page,
            'invitations' => $invitations,
            'unreadCount' => (clone $query)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, PlanOpsNotification $notification): RedirectResponse
    {
        $owned = PlanOpsNotification::query()->forRecipient($request->user())->whereKey($notification->getKey())->firstOrFail();
        $owned->forceFill(['read_at' => $owned->read_at ?? now()])->save();

        return back()->with('status', 'Notification marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        PlanOpsNotification::query()->forRecipient($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }

    public function acceptInvitation(Request $request, PlanOpsNotification $notification, AcceptProjectInvitation $accept): RedirectResponse
    {
        $owned = PlanOpsNotification::query()->forRecipient($request->user())->whereKey($notification->getKey())->firstOrFail();
        abort_unless($owned->event_type === NotificationEventType::INVITATION_CREATED && $owned->target_type === 'project_invitation', 404);

        $invitation = ProjectInvitation::query()->whereKey($owned->target_id)->firstOrFail();
        $accept->handleInvitation($request->user(), $invitation);
        $owned->forceFill(['read_at' => $owned->read_at ?? now()])->save();

        return to_route('projects.index')->with('status', 'You joined the project.');
    }

    public function declineInvitation(Request $request, PlanOpsNotification $notification, DeclineProjectInvitation $decline): RedirectResponse
    {
        $owned = PlanOpsNotification::query()->forRecipient($request->user())->whereKey($notification->getKey())->firstOrFail();
        abort_unless($owned->event_type === NotificationEventType::INVITATION_CREATED && $owned->target_type === 'project_invitation', 404);

        $decline->handle($request->user(), ProjectInvitation::query()->whereKey($owned->target_id)->firstOrFail());
        $owned->forceFill(['read_at' => $owned->read_at ?? now()])->save();

        return to_route('notifications.index')->with('status', 'Invitation declined.');
    }
}
