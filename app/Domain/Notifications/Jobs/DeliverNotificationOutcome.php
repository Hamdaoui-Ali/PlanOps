<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Actions\PersistNotificationOutcome;
use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
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
        $persist->handle($this->outcome);
    }
}
