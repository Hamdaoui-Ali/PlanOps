<?php

namespace App\Domain\Collaboration\ValueObjects;

use App\Domain\Collaboration\Enums\ProjectRole;

final readonly class TeamWorkMember
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public ProjectRole $role,
        public int $activeCount,
        public int $blockedCount,
        public int $overdueCount,
        public int $completedThisWeekCount,
    ) {}
}
