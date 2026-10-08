<?php

namespace Tests\Unit;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use App\Services\LoanCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LoanCalculatorTest extends TestCase
{
    private function schedule(
        InterestMethod $method,
        PaymentFrequency $freq,
        float $rate,
        int $term,
        TermUnit $unit = TermUnit::Months,
        RateBasis $basis = RateBasis::PerMonth,
        bool $sundays = true,
    ): array {
        return (new LoanCalculator($sundays))->schedule(10000, $rate, $basis, $method, $freq, $term, $unit, CarbonImmutable::parse('2026-01-01'));
    }

    public function test_daily_collection_20_percent_in_60_days(): void
    {
        // The Client's typical loan: ₱10,000 at 20% for the whole term, collected daily for 60 days.
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Daily, 20, 60, TermUnit::Days, RateBasis::PerTerm);

        $this->assertSame(60, $s['installment_count']);
        $this->assertSame('2000.00', $s['total_interest']);
        $this->assertSame('12000.00', $s['total_payable']);
        $this->assertSame('200.00', $s['installments'][0]['amount_due']);
        $this->assertSame('2026-01-02', $s['installments'][0]['due_date']->toDateString());
        $this->assertSame('2026-03-02', $s['maturity_date']->toDateString());
        $this->assertSame('0.00', end($s['installments'])['remaining_balance']);
    }

    public function test_daily_collection_includes_sundays_by_default(): void
    {
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Daily, 20, 7, TermUnit::Days, RateBasis::PerTerm);

        $sundays = array_filter($s['installments'], fn ($r) => $r['due_date']->isSunday());
        $this->assertCount(1, $sundays);
    }

    public function test_daily_collection_can_skip_sundays(): void
    {
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Daily, 20, 60, TermUnit::Days, RateBasis::PerTerm, sundays: false);

        $this->assertSame(60, $s['installment_count']);
        foreach ($s['installments'] as $row) {
            $this->assertFalse($row['due_date']->isSunday());
        }
    }

    public function test_daily_uneven_amounts_sum_exactly(): void
    {
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Daily, 15, 45, TermUnit::Days, RateBasis::PerTerm);

        $this->assertSame('11500.00', $s['total_payable']);
        $this->assertEqualsWithDelta(11500, array_sum(array_column($s['installments'], 'amount_due')), 0.001);
        $this->assertSame('0.00', end($s['installments'])['remaining_balance']);
    }

    public function test_flat_monthly_totals_and_rounding(): void
    {
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Monthly, 5, 3);

        $this->assertSame('1500.00', $s['total_interest']);
        $this->assertSame(['3833.33', '3833.33', '3833.34'], array_column($s['installments'], 'amount_due'));
        $this->assertSame('2026-04-01', $s['maturity_date']->toDateString());
    }

    public function test_flat_weekly_installments_sum_to_total(): void
    {
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Weekly, 5, 3);

        $this->assertSame(13, $s['installment_count']);
        $this->assertEqualsWithDelta(11500, array_sum(array_column($s['installments'], 'amount_due')), 0.001);
        $this->assertSame('2026-01-08', $s['installments'][0]['due_date']->toDateString());
    }

    public function test_diminishing_monthly_matches_standard_amortization(): void
    {
        $s = $this->schedule(InterestMethod::Diminishing, PaymentFrequency::Monthly, 3, 12);

        $this->assertSame('300.00', $s['installments'][0]['interest']);
        $this->assertSame('1004.62', $s['installments'][0]['amount_due']);
        $this->assertEqualsWithDelta(10000, array_sum(array_column($s['installments'], 'principal')), 0.001);
        $this->assertSame('0.00', end($s['installments'])['remaining_balance']);
    }

    public function test_rate_bases_are_equivalent(): void
    {
        $perMonth = $this->schedule(InterestMethod::Flat, PaymentFrequency::Monthly, 2, 6);
        $perTerm = $this->schedule(InterestMethod::Flat, PaymentFrequency::Monthly, 12, 6, basis: RateBasis::PerTerm);
        $perYear = $this->schedule(InterestMethod::Flat, PaymentFrequency::Monthly, 24, 6, basis: RateBasis::PerYear);

        $this->assertSame($perMonth['total_interest'], $perTerm['total_interest']);
        $this->assertSame($perMonth['total_interest'], $perYear['total_interest']);
    }

    public function test_per_month_rate_on_a_term_in_days(): void
    {
        // 10% a month for 60 days = 20%.
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::Daily, 10, 60, TermUnit::Days, RateBasis::PerMonth);

        $this->assertSame('2000.00', $s['total_interest']);
    }

    public function test_semi_monthly_due_dates(): void
    {
        $s = $this->schedule(InterestMethod::Flat, PaymentFrequency::SemiMonthly, 5, 1);

        $this->assertSame(['2026-01-16', '2026-02-01'], array_map(fn ($r) => $r['due_date']->toDateString(), $s['installments']));
    }
}
