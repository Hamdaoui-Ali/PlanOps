<?php

namespace App\Domain\Collaboration\ValueObjects;

use App\Domain\Projects\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** @param Collection<int, TeamWorkMember> $members */
final readonly class TeamWorkSnapshot
{
    public function __construct(
        public Project $project,
        public Collection $members,
        public int $unassignedCount,
        public CarbonImmutable $weekStart,
        public CarbonImmutable $weekEnd,
    ) {}
}
