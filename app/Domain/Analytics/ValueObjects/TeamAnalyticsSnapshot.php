<?php

namespace App\Domain\Analytics\ValueObjects;

use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Domain\Projects\Models\Project;
use Illuminate\Support\Collection;

final readonly class TeamAnalyticsSnapshot
{
    /**
     * @param array<string, int> $throughput
     * @param array<string, int> $statusDistribution
     * @param array{active: int, blocked: int, overdue: int, unassigned: int} $currentWorkload
     * @param Collection<int, array{label: string, created: int, completed: int, blocked: int}> $weeklyFlow
     */
    public function __construct(
        public Project $project,
        public ReportPeriod $reportPeriod,
        public array $throughput,
        public array $statusDistribution,
        public array $currentWorkload,
        public Collection $weeklyFlow,
        public bool $hasRecordedMovement,
    ) {}
}
