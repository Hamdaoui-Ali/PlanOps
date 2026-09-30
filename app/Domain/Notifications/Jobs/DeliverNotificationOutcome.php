<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Collaboration\Models\ProjectMembership;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Notifications\Actions\PersistNotificationOutcome;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Enums\NotificationEventType;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
use App\Notifications\PlanOpsNotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverNotificationOutcome implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly NotificationOutcome $outcome) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function failed(\Throwable $exception): void
    {
        NotificationDeliveryFailure::query()->create([
            'event_type' => $this->outcome->eventType,
            'recipient_id' => $this->outcome->recipientId,
            'project_id' => $this->outcome->projectId,
            'attempts' => $this->attempts(),
            'exception_class' => $exception::class,
            'message' => 'Notification delivery failed.',
        ]);
    }

    public function handle(PersistNotificationOutcome $persist): void
    {
        $recipient = User::query()->find($this->outcome->recipientId);
        if (! $this->recipientCanReceive($recipient)) {
            return;
        }

        $outcome = $this->authorizedOutcome($recipient);
        $persist->handle($outcome);

        if ($outcome->targetId !== null) {
            $recipient->notify(new PlanOpsNotificationMail($outcome));
        }
    }

    private function recipientCanReceive(?User $recipient): bool
    {
        if ($recipient === null || $recipient->deactivated_at !== null) {
            return false;
        }

        return $this->outcome->eventType !== NotificationEventType::ASSIGNEE_CHANGED
            || ProjectMembership::query()
                ->where('project_id', $this->outcome->projectId)
                ->where('user_id', $recipient->getKey())
                ->whereNull('removed_at')
                ->exists();
    }

    private function authorizedOutcome(User $recipient): NotificationOutcome
    {
        $targetIsSafe = match ($this->outcome->eventType) {
            NotificationEventType::INVITATION_CREATED => ProjectInvitation::query()
                ->whereKey($this->outcome->targetId)
                ->where('project_id', $this->outcome->projectId)
                ->whereRaw('LOWER(normalized_email) = ?', [strtolower($recipient->email)])
                ->whereNull('accepted_at')->whereNull('revoked_at')
                ->where('expires_at', '>', now())->exists(),
            NotificationEventType::ASSIGNEE_CHANGED => Task::query()
                ->accessibleBy($recipient)
                ->where('project_id', $this->outcome->projectId)
                ->where('assignee_id', $recipient->getKey())
                ->whereKey($this->outcome->targetId)->exists(),
        };

        return $targetIsSafe ? $this->outcome : $this->outcome->withoutTarget();
    }
}
