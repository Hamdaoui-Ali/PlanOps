<?php

namespace App\Domain\Tasks\Enums;

enum TaskStatus: string
{
    case BACKLOG = 'BACKLOG';
    case NOT_STARTED = 'NOT_STARTED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case IN_REVIEW = 'IN_REVIEW';
    case BLOCKED = 'BLOCKED';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';

    public function category(): string
    {
        return match ($this) {
            self::BACKLOG, self::NOT_STARTED => 'PLANNED',
            self::IN_PROGRESS, self::IN_REVIEW, self::BLOCKED => 'ACTIVE',
            self::DONE, self::CANCELLED => 'TERMINAL',
        };
    }
}
