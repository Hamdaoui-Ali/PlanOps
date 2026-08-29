<?php

namespace App\Domain\Analytics\ValueObjects;

use App\Domain\Identity\ValueObjects\ReportPeriod;
use Illuminate\Support\Collection;

final readonly class AnalyticsSnapshot
{
    /** @param array<string, int> $throughput @param array<string, float> $timeInStatus */
    public function __construct(
        public ReportPeriod $reportPeriod,
        public array $throughput,
        public ?float $leadTimeMedianHours,
        public ?float $cycleTimeMedianHours,
        public array $timeInStatus,
        public Collection $projectContribution,
    ) {
    }
}
