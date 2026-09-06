<x-app-layout>
    <section class="my-work-page notifications-page" aria-labelledby="notifications-heading">
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

        <ul class="notification-list" aria-label="Notification list">
            @forelse ($notifications as $notification)
                <li class="notification-card {{ $notification->read_at === null ? 'is-unread' : '' }}" data-notification-id="{{ $notification->id }}">
                    <div class="notification-card-icon" aria-hidden="true"><i class="ph {{ $notification->event_type === \App\Domain\Notifications\Enums\NotificationEventType::INVITATION_CREATED ? 'ph-users-three' : 'ph-bell' }}"></i></div>
                    <div class="notification-card-body">
                        <div class="notification-card-meta">
                            <span class="notification-card-type">{{ $notification->event_type === \App\Domain\Notifications\Enums\NotificationEventType::INVITATION_CREATED ? 'Project invitation' : 'Workspace update' }}</span>
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
                        </div>
                    @if ($notification->event_type === \App\Domain\Notifications\Enums\NotificationEventType::INVITATION_CREATED && $invitations->has($notification->target_id))
                        @php($invitation = $invitations->get($notification->target_id))
                        <h2>{{ $invitation?->invitedBy?->name ?? 'A project manager' }} invited you to collaborate</h2>
                        <p class="notification-card-description">You have been invited to <strong>{{ $notification->data['project_name'] ?? 'a project' }}</strong> as a project {{ strtolower($invitation?->role?->value ?? 'member') }}.</p>
                        @if ($invitation?->isPending())
                            <div class="notification-card-actions">
                                <form method="POST" action="{{ route('notifications.accept-invitation', $notification) }}">
                                    @csrf
                                    <button type="submit" class="planops-button planops-button-primary"><i class="ph ph-check" aria-hidden="true"></i>Accept invitation</button>
                                </form>
                                <form method="POST" action="{{ route('notifications.decline-invitation', $notification) }}">
                                    @csrf
                                    <button type="submit" class="planops-button planops-button-secondary"><i class="ph ph-x" aria-hidden="true"></i>Decline</button>
                                </form>
                            </div>
                        @else
                            <span class="notification-card-state">Invitation no longer pending</span>
                        @endif
                    @else
                        <h2>{{ $notification->data['message'] ?? $notification->event_type->value }}</h2>
                    @endif
                    </div>
                    <div class="notification-card-status">
                    @if ($notification->read_at === null)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="planops-button planops-button-secondary">Mark as read</button>
                        </form>
                    @else
                        <span class="notification-read-state"><i class="ph ph-check-circle" aria-hidden="true"></i>Read</span>
                    @endif
                    </div>
                </li>
            @empty
                <li role="status">No notifications yet.</li>
            @endforelse
        </ul>

        {{ $notifications->links() }}
    </section>
</x-app-layout>
