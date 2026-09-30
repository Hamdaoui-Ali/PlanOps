<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Models\PlanOpsNotification;
use Illuminate\Support\Facades\DB;

class PersistNotificationOutcome
{
    public function handle(NotificationOutcome $outcome): PlanOpsNotification
    {
        return DB::transaction(function () use ($outcome): PlanOpsNotification {
            $notification = PlanOpsNotification::query()->firstOrCreate(
                ['idempotency_key' => $outcome->idempotencyKey()],
                PlanOpsNotification::fromOutcome($outcome),
            );

            if ($outcome->targetId === null && ($notification->target_type !== null || $notification->target_id !== null)) {
                $notification->forceFill(['target_type' => null, 'target_id' => null])->save();
            }

            return $notification;
        });
    }

    public function redactExisting(NotificationOutcome $outcome): void
    {
        PlanOpsNotification::query()
            ->where('idempotency_key', $outcome->idempotencyKey())
            ->update(['target_type' => null, 'target_id' => null]);
    }
}
