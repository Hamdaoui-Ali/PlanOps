<x-app-layout>
    <section class="my-work-page" aria-labelledby="notifications-heading">
        <header class="my-work-header">
            <div>
                <p class="planops-eyebrow">Workspace</p>
                <h1 id="notifications-heading">Notifications</h1>
                <p>You have {{ $unreadCount }} unread notification{{ $unreadCount === 1 ? '' : 's' }}.</p>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="planops-button planops-button-secondary">Mark all as read</button>
            </form>
        </header>

        @if (session('status'))
            <p role="status" class="planops-status">{{ session('status') }}</p>
        @endif

        <ul aria-label="Notification list">
            @forelse ($notifications as $notification)
                <li>
                    @if ($notification->event_type === \App\Domain\Notifications\Enums\NotificationEventType::INVITATION_CREATED && $invitations->has($notification->target_id))
                        @php($invitation = $invitations->get($notification->target_id))
                        <p>
                            {{ $invitation?->invitedBy?->name ?? 'A project manager' }} invited you to join
                            {{ $notification->data['project_name'] ?? 'a project' }}.
                        </p>
                        @if ($invitation?->isPending())
                            <form method="POST" action="{{ route('notifications.accept-invitation', $notification) }}">
                                @csrf
                                <button type="submit" class="planops-button planops-button-primary">Accept invitation</button>
                            </form>
                            <form method="POST" action="{{ route('notifications.decline-invitation', $notification) }}">
                                @csrf
                                <button type="submit" class="planops-button planops-button-secondary">Decline invitation</button>
                            </form>
                        @endif
                    @else
                        <p>{{ $notification->data['message'] ?? $notification->event_type->value }}</p>
                    @endif
                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="planops-button planops-button-secondary">Mark as read</button>
                        </form>
                    @else
                        <span>Read</span>
                    @endif
                </li>
            @empty
                <li role="status">No notifications yet.</li>
            @endforelse
        </ul>

        {{ $notifications->links() }}
    </section>
</x-app-layout>
