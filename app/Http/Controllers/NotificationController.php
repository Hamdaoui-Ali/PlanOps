<?php

namespace App\Http\Controllers;

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

        return view('pages.notifications.index', [
            'notifications' => $query->paginate(30)->withQueryString(),
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
}
