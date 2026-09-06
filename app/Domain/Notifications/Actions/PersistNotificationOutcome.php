<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Models\PlanOpsNotification;
use Illuminate\Support\Facades\DB;

class PersistNotificationOutcome
{
    public function handle(NotificationOutcome $outcome): PlanOpsNotification
    {
        return DB::transaction(fn (): PlanOpsNotification => PlanOpsNotification::query()->firstOrCreate(
            ['idempotency_key' => $outcome->idempotencyKey()],
            PlanOpsNotification::fromOutcome($outcome),
        ));
    }
}
