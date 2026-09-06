<x-guest-layout>
    <div class="p-6">
        @if ($invitation && $invitation->isPending())
            <h1>Join {{ $invitation->project->name }}</h1><p>You have been invited to join this project.</p>
            @auth <form method="POST" action="{{ route('invitations.accept', request()->route('token')) }}">@csrf<button type="submit">Accept invitation</button></form>
            @else <p>Sign in with the invited email address to accept.</p> @endauth
        @else <h1>This invitation is no longer available.</h1> @endif
    </div>
</x-guest-layout>
