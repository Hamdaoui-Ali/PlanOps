<?php

namespace App\Domain\Notifications\Queries;

use App\Domain\Notifications\Models\PlanOpsNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class NotificationCenterQuery
{
    public function for(User $recipient): Builder
    {
        return PlanOpsNotification::query()->forRecipient($recipient)->latest('created_at');
    }
}
