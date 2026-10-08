<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LoanServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeLoan(string $start = '2026-01-01'): Loan
    {
        $area = Area::create(['name' => 'North', 'code' => 'N']);
        $borrower = Borrower::create(['area_id' => $area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);

        return app(LoanService::class)->create([
            'borrower_id' => $borrower->id,
            'principal' => 10000,
            'interest_rate' => 5,
            'rate_basis' => 'per_month',
            'interest_method' => 'flat',
            'payment_frequency' => 'monthly',
            'term' => 3, 'term_unit' => 'months',
            'start_date' => $start,
        ]);
    }

    public function test_creating_a_loan_builds_its_schedule(): void
    {
        $loan = $this->makeLoan();

        $this->assertSame('11500.00', $loan->balance);
        $this->assertSame($loan->area_id, $loan->borrower->area_id);
        $this->assertCount(3, $loan->installments);
    }

    public function test_payments_apply_to_oldest_installment_and_mark_paid(): void
    {
        Carbon::setTestNow('2026-01-15');
        $loan = $this->makeLoan();
        $service = app(LoanService::class);

        $service->recordPayment($loan, ['amount' => 5000, 'payment_date' => '2026-01-15', 'receipt_no' => '0001']);
        $loan->refresh();

        $this->assertSame('6500.00', $loan->balance);
        $this->assertSame('3833.33', $loan->installments[0]->amount_paid);
        $this->assertSame('1166.67', $loan->installments[1]->amount_paid);
        $this->assertSame(LoanStatus::Active, $loan->status);

        $service->recordPayment($loan, ['amount' => 6500, 'payment_date' => '2026-01-20', 'receipt_no' => '0002']);
        $this->assertSame(LoanStatus::Paid, $loan->refresh()->status);
    }

    public function test_overpayment_is_rejected(): void
    {
        $loan = $this->makeLoan();

        $this->expectException(ValidationException::class);
        app(LoanService::class)->recordPayment($loan, ['amount' => 20000, 'payment_date' => '2026-01-15', 'receipt_no' => '0003']);
    }

    public function test_deleting_a_payment_restores_balance(): void
    {
        $loan = $this->makeLoan();
        $service = app(LoanService::class);
        $payment = $service->recordPayment($loan, ['amount' => 5000, 'payment_date' => '2026-01-15', 'receipt_no' => '0001']);

        $service->deletePayment($payment);

        $this->assertSame('11500.00', $loan->refresh()->balance);
        $this->assertSame('0.00', $loan->installments[0]->amount_paid);
    }

    public function test_missed_installment_marks_loan_overdue(): void
    {
        $loan = $this->makeLoan('2026-01-01');
        Carbon::setTestNow('2026-02-10');

        app(LoanService::class)->refreshStatuses();

        $this->assertSame(LoanStatus::Overdue, $loan->refresh()->status);
    }
}
