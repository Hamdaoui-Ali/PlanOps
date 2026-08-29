<?php

namespace App\Domain\Identity\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReportPeriod
{
    public function __construct(
        public string $label,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $bucket,
    ) {
        if ($this->end->lessThanOrEqualTo($this->start)) {
            throw new InvalidArgumentException('A report period end must be after its start.');
        }

        if (! in_array($this->bucket, ['day', 'week', 'month'], true)) {
            throw new InvalidArgumentException('Report period buckets must be day, week, or month.');
        }
    }
}
