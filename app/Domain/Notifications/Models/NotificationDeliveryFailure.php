<?php

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDeliveryFailure extends Model
{
    protected $fillable = [
        'event_type', 'recipient_id', 'project_id', 'attempts',
        'exception_class', 'message',
    ];
}
