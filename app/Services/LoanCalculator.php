<?php

namespace App\Services;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use Carbon\CarbonInterface;

/**
 * Builds a repayment schedule. All money math is done in centavos (integers)
 * so installments always add up exactly to the loan totals.
 */
class LoanCalculator
{
    public function __construct(private bool $collectOnSundays = true) {}

    /**
     * @return array{
     *     installment_count: int,
     *     total_interest: string,
     *     total_payable: string,
     *     maturity_date: CarbonInterface,
     *     installments: list<array{number: int, due_date: CarbonInterface, principal: string, interest: string, amount_due: string, remaining_balance: string}>
     * }
     */
    public function schedule(
        float|string $principal,
        float|string $interestRate,
        RateBasis $rateBasis,
        InterestMethod $method,
        PaymentFrequency $frequency,
        int $term,
        TermUnit $termUnit,
        CarbonInterface $startDate,
    ): array {
        $principalCents = (int) round((float) $principal * 100);
        $count = $frequency->installmentsFor($term, $termUnit, $startDate, $this->collectOnSundays);
        $termRate = $this->termRate((float) $interestRate, $rateBasis, $termUnit->toMonths($term));

        $rows = $method === InterestMethod::Flat
            ? $this->flat($principalCents, $termRate, $count)
            : $this->diminishing($principalCents, $termRate, $count);

        $totalInterest = array_sum(array_column($rows, 1));
        $balance = $principalCents + $totalInterest;

        $installments = [];
        foreach ($rows as $i => [$p, $int]) {
            $balance -= $p + $int;
            $installments[] = [
                'number' => $i + 1,
                'due_date' => $frequency->dueDate($startDate, $i + 1, $this->collectOnSundays),
                'principal' => $this->money($p),
                'interest' => $this->money($int),
                'amount_due' => $this->money($p + $int),
                'remaining_balance' => $this->money($balance),
            ];
        }

        return [
            'installment_count' => $count,
            'total_interest' => $this->money($totalInterest),
            'total_payable' => $this->money($principalCents + $totalInterest),
            'maturity_date' => end($installments)['due_date'],
            'installments' => $installments,
        ];
    }

    /** Interest rate for the whole loan term as a fraction (20% → 0.20). */
    private function termRate(float $rate, RateBasis $basis, float $termMonths): float
    {
        $fraction = $rate / 100;

        return match ($basis) {
            RateBasis::PerTerm => $fraction,
            RateBasis::PerMonth => $fraction * $termMonths,
            RateBasis::PerYear => $fraction * $termMonths / 12,
        };
    }

    /**
     * Flat / add-on: interest = principal × term rate, spread evenly over the installments.
     * The total payable is split first so collectors get even amounts (₱12,000 / 60 = ₱200.00).
     *
     * @return list<array{int, int}> [principal, interest] in centavos
     */
    private function flat(int $principal, float $termRate, int $count): array
    {
        $totalInterest = (int) round($principal * $termRate);

        $amounts = $this->split($principal + $totalInterest, $count);
        $interests = $this->split($totalInterest, $count);

        return array_map(fn ($amount, $interest) => [$amount - $interest, $interest], $amounts, $interests);
    }

    /**
     * Diminishing balance: equal amortization, interest charged on the remaining principal.
     *
     * @return list<array{int, int}> [principal, interest] in centavos
     */
    private function diminishing(int $principal, float $termRate, int $count): array
    {
        $periodRate = $termRate / $count;

        $payment = $periodRate > 0
            ? $principal * $periodRate / (1 - (1 + $periodRate) ** -$count)
            : $principal / $count;

        $rows = [];
        $remaining = $principal;

        for ($n = 1; $n <= $count; $n++) {
            $interest = (int) round($remaining * $periodRate);
            $principalPart = $n === $count ? $remaining : min($remaining, (int) round($payment) - $interest);
            $remaining -= $principalPart;
            $rows[] = [$principalPart, $interest];
        }

        return $rows;
    }

    /** Split centavos evenly; the last installment absorbs the rounding difference. */
    private function split(int $total, int $count): array
    {
        $each = intdiv($total, $count);
        $parts = array_fill(0, $count, $each);
        $parts[$count - 1] += $total - $each * $count;

        return $parts;
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
