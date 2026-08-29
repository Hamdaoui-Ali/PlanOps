<?php

namespace App\Domain\Dashboard\ValueObjects;

use App\Domain\Activity\Models\TaskActivity;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use Illuminate\Database\Eloquent\Collection;

final readonly class DashboardSnapshot
{
    /** @param array<string, int> $statusCounts @param array{created:int,completed:int,balance:int} $period */
    public function __construct(
        public ReportPeriod $reportPeriod,
        public int $activeProjects,
        public array $statusCounts,
        public int $overdueCount,
        public array $period,
        public Collection $recentActivity,
    ) {
    }
}
