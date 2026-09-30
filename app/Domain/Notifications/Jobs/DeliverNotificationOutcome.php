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
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function () use ($persist): void {
            $recipient = User::query()->lockForUpdate()->find($this->outcome->recipientId);
            $outcome = $this->outcomeForDelivery($recipient);

            if ($outcome === null) {
                $persist->redactExisting($this->outcome);

                return;
            }

            $persist->handle($outcome);

            if ($outcome->targetId !== null) {
                $recipient->notify(new PlanOpsNotificationMail($outcome));
            }
        });
    }

    private function outcomeForDelivery(?User $recipient): ?NotificationOutcome
    {
        if ($recipient === null || $recipient->deactivated_at !== null) {
            return null;
        }

        return match ($this->outcome->eventType) {
            NotificationEventType::INVITATION_CREATED => $this->invitationOutcome($recipient),
            NotificationEventType::ASSIGNEE_CHANGED => $this->assignmentOutcome($recipient),
        };
    }

    private function invitationOutcome(User $recipient): NotificationOutcome
    {
        $invitation = ProjectInvitation::query()
            ->whereKey($this->outcome->targetId)
            ->where('project_id', $this->outcome->projectId)
            ->whereRaw('LOWER(normalized_email) = ?', [strtolower($recipient->email)])
            ->whereNull('accepted_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        return $invitation !== null ? $this->outcome : $this->outcome->withoutTarget();
    }

    private function assignmentOutcome(User $recipient): ?NotificationOutcome
    {
        $task = Task::query()
            ->accessibleBy($recipient)
            ->where('project_id', $this->outcome->projectId)
            ->whereKey($this->outcome->targetId)
            ->lockForUpdate()
            ->first();

        $activeMembership = ProjectMembership::query()
            ->where('project_id', $this->outcome->projectId)
            ->where('user_id', $recipient->getKey())
            ->whereNull('removed_at')
            ->lockForUpdate()
            ->first();

        if ($activeMembership === null) {
            return null;
        }

        return $task !== null && (string) $task->assignee_id === (string) $recipient->getKey()
            ? $this->outcome
            : $this->outcome->withoutTarget();
    }
}
