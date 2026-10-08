<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasLabel;

enum PaymentFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case SemiMonthly = 'semi_monthly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::SemiMonthly => 'Semi-monthly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Number of installments in a loan term. For daily loans a term in days is the
     * number of collection days (e.g. "60 days" = 60 daily payments).
     */
    public function installmentsFor(int $term, TermUnit $unit, CarbonInterface $start, bool $collectOnSundays = true): int
    {
        $start = $start->toImmutable();

        $count = match ($this) {
            self::Daily => $unit === TermUnit::Days
                ? $term
                : $this->collectionDaysBetween($start, $start->addMonthsNoOverflow($term), $collectOnSundays),
            self::Weekly => $unit === TermUnit::Days ? intdiv($term, 7) : (int) round($term * 52 / 12),
            self::SemiMonthly => $unit === TermUnit::Days ? (int) round($term / 15) : $term * 2,
            self::Monthly => $unit === TermUnit::Days ? (int) round($term / 30) : $term,
        };

        return max(1, $count);
    }

    /** Due date of installment $n (1-based) counted from the loan release date. */
    public function dueDate(CarbonInterface $start, int $n, bool $collectOnSundays = true): CarbonInterface
    {
        $start = $start->toImmutable();

        return match ($this) {
            self::Daily => $collectOnSundays ? $start->addDays($n) : $this->addDaysSkippingSundays($start, $n),
            self::Weekly => $start->addWeeks($n),
            self::SemiMonthly => $start->addMonthsNoOverflow(intdiv($n, 2))->addDays($n % 2 ? 15 : 0),
            self::Monthly => $start->addMonthsNoOverflow($n),
        };
    }

    private function addDaysSkippingSundays(CarbonInterface $date, int $days): CarbonInterface
    {
        while ($days > 0) {
            $date = $date->addDay();
            if (! $date->isSunday()) {
                $days--;
            }
        }

        return $date;
    }

    private function collectionDaysBetween(CarbonInterface $from, CarbonInterface $to, bool $collectOnSundays): int
    {
        $days = (int) $from->diffInDays($to);

        if ($collectOnSundays) {
            return $days;
        }

        $count = 0;
        for ($d = $from->addDay(); $d->lte($to); $d = $d->addDay()) {
            if (! $d->isSunday()) {
                $count++;
            }
        }

        return $count;
    }
}
