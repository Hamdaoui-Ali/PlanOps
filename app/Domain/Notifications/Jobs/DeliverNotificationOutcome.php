<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Actions\PersistNotificationOutcome;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Domain\Collaboration\Models\ProjectInvitation;
use App\Domain\Notifications\Enums\NotificationEventType;
use App\Domain\Tasks\Models\Task;
use App\Models\User;
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
        $persist->handle($this->authorizedOutcome());
    }

    private function authorizedOutcome(): NotificationOutcome
    {
        $recipient = User::query()->find($this->outcome->recipientId);
        if ($recipient === null) {
            return $this->outcome->withoutTarget();
        }

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
                ->whereKey($this->outcome->targetId)->exists(),
        };

        return $targetIsSafe ? $this->outcome : $this->outcome->withoutTarget();
    }
}
