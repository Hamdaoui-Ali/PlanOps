<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\WeekStartDay;
use App\Domain\Identity\ValueObjects\ReportPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class UserPeriodResolver
{
    public function today(User $user, ?CarbonImmutable $now = null): ReportPeriod
    {
        $localNow = $this->localNow($user, $now);
        $start = $localNow->startOfDay();

        return $this->period('Today', $start, $start->addDay(), 'day');
    }

    public function week(User $user, ?CarbonImmutable $now = null): ReportPeriod
    {
        $localNow = $this->localNow($user, $now);
        $startDay = ($user->preference?->week_start_day ?? WeekStartDay::MONDAY) === WeekStartDay::SUNDAY
            ? CarbonInterface::SUNDAY
            : CarbonInterface::MONDAY;
        $start = $localNow->startOfWeek($startDay);

        return $this->period('Week of '.$start->format('M j, Y'), $start, $start->addWeek(), 'week');
    }

    public function month(User $user, ?CarbonImmutable $now = null): ReportPeriod
    {
        $start = $this->localNow($user, $now)->startOfMonth();

        return $this->period($start->format('F Y'), $start, $start->addMonth(), 'month');
    }

    public function year(User $user, ?CarbonImmutable $now = null): ReportPeriod
    {
        $start = $this->localNow($user, $now)->startOfYear();

        return $this->period($start->format('Y'), $start, $start->addYear(), 'month');
    }

    public function custom(User $user, string $from, string $until): ReportPeriod
    {
        $timezone = $this->timezone($user);
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
        $inclusiveEnd = CarbonImmutable::createFromFormat('!Y-m-d', $until, $timezone);

        if ($start === false || $inclusiveEnd === false || $inclusiveEnd->lessThan($start)) {
            throw new InvalidArgumentException('A custom report period must have a valid non-reversed date range.');
        }

        $end = $inclusiveEnd->addDay();
        $days = $start->diffInDays($end);
        $bucket = $days === 1 ? 'day' : ($days <= 7 ? 'week' : 'month');
        $label = $start->isSameDay($inclusiveEnd)
            ? $start->format('M j, Y')
            : $start->format('M j, Y').' – '.$inclusiveEnd->format('M j, Y');

        return $this->period($label, $start, $end, $bucket);
    }

    private function localNow(User $user, ?CarbonImmutable $now): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now($this->timezone($user)))->setTimezone($this->timezone($user));
    }

    private function timezone(User $user): string
    {
        return $user->preference?->timezone ?? 'Africa/Casablanca';
    }

    private function period(string $label, CarbonImmutable $start, CarbonImmutable $end, string $bucket): ReportPeriod
    {
        return new ReportPeriod($label, $start->utc(), $end->utc(), $bucket);
    }
}
