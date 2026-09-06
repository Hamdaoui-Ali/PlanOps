<?php

namespace App\Domain\Notifications\Enums;

enum NotificationEventType: string
{
    case INVITATION_CREATED = 'INVITATION_CREATED';
    case ASSIGNEE_CHANGED = 'ASSIGNEE_CHANGED';
}
