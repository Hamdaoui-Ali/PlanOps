<?php

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Actions\PersistNotificationOutcome;
use App\Domain\Notifications\Data\NotificationOutcome;
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

    public function handle(PersistNotificationOutcome $persist): void
    {
        $persist->handle($this->outcome);
    }
}
