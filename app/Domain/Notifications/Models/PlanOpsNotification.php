<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Data\NotificationOutcome;
use App\Domain\Notifications\Enums\NotificationEventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanOpsNotification extends Model
{
    protected $table = 'planops_notifications';

    protected $fillable = [
        'recipient_id', 'event_type', 'idempotency_key', 'project_id',
        'target_type', 'target_id', 'data', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => NotificationEventType::class,
            'data' => 'array',
            'read_at' => 'immutable_datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function scopeForRecipient(Builder $query, User|int $recipient): Builder
    {
        return $query->where('recipient_id', $recipient instanceof User ? $recipient->getKey() : $recipient);
    }

    public static function fromOutcome(NotificationOutcome $outcome): array
    {
        return [
            'recipient_id' => $outcome->recipientId,
            'event_type' => $outcome->eventType,
            'idempotency_key' => $outcome->idempotencyKey(),
            'project_id' => $outcome->projectId,
            'target_type' => $outcome->targetType,
            'target_id' => $outcome->targetId,
            'data' => $outcome->toPayload(),
        ];
    }
}
