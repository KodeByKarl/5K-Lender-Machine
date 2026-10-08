<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Filament\Resources\Loans\RelationManagers\PaymentsRelationManager;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use App\Services\SavingsService;
use App\Support\AmountInWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptsTest extends TestCase
{
    use RefreshDatabase;

    private Area $north;

    private Area $south;

    private LoanService $loans;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-20 10:00');

        $this->north = Area::create(['name' => 'North', 'code' => 'N']);
        $this->south = Area::create(['name' => 'South', 'code' => 'S']);
        $this->loans = app(LoanService::class);
        $this->actingAs(User::create(['name' => 'Owner', 'email' => 'o@test', 'password' => 'x', 'role' => UserRole::Admin]));
    }

    private function loan(?Area $area = null, string $name = 'Cruz'): Loan
    {
        $borrower = Borrower::create(['area_id' => ($area ?? $this->north)->id, 'first_name' => 'Juan', 'last_name' => $name]);

        return $this->loans->create([
            'borrower_id' => $borrower->id, 'principal' => 10000, 'interest_rate' => 20, 'rate_basis' => 'per_term',
            'interest_method' => 'flat', 'payment_frequency' => 'daily', 'term' => 60, 'term_unit' => 'days', 'start_date' => '2026-03-01',
        ]);
    }

    private function pay(Loan $loan, string $receipt, string $date = '2026-03-02', float $amount = 200)
    {
        return $this->loans->recordPayment($loan, ['amount' => $amount, 'payment_date' => $date, 'receipt_no' => $receipt]);
    }

    private function expectError(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_payment_requires_a_receipt_number(): void
    {
        $loan = $this->loan();

        $this->expectError('receipt_no', fn () => $this->pay($loan, '  '));
        $this->assertSame(0, $loan->payments()->count());
    }

    public function test_receipt_numbers_are_unique_per_area_across_payments_and_deposits(): void
    {
        $loan = $this->loan();
        $this->pay($loan, ' or-1001 ');

        // Same number, any spacing or case, in the same Area: rejected for another payment…
        $this->expectError('receipt_no', fn () => $this->pay($this->loan(name: 'Santos'), 'OR-1001'));

        // …and for a savings deposit.
        $savings = app(SavingsService::class);
        $account = $savings->open($loan->borrower, '2026-03-01');
        $this->expectError('reference_no', fn () => $savings->deposit($account, ['amount' => 50, 'transaction_date' => '2026-03-02', 'reference_no' => 'or-1001']));

        // A different Area has its own booklet.
        $this->assertSame('OR-1001', $this->pay($this->loan($this->south, 'Reyes'), 'OR-1001')->receipt_no);
    }

    public function test_payments_cannot_be_back_dated_before_the_latest_one(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '0001', '2026-03-05');

        $this->expectError('payment_date', fn () => $this->pay($loan, '0002', '2026-03-04'));
        $this->expectError('payment_date', fn () => $this->pay($this->loan(name: 'Santos'), '0003', '2026-02-28')); // before release

        $this->pay($loan, '0002', '2026-03-05'); // same day is fine
        $this->assertSame(2, $loan->payments()->count());
    }

    public function test_only_the_latest_payment_can_be_deleted(): void
    {
        $loan = $this->loan();
        $first = $this->pay($loan, '0001', '2026-03-02');
        $last = $this->pay($loan, '0002', '2026-03-03');

        $this->expectError('payment', fn () => $this->loans->deletePayment($first));

        $this->loans->deletePayment($last);
        $this->assertSame('11800.00', $loan->refresh()->balance);
        $this->assertSame('11800.00', $first->refresh()->balance_after); // the earlier receipt is unchanged
    }

    public function test_fixing_a_receipt_number_keeps_amounts_and_rejects_duplicates(): void
    {
        $loan = $this->loan();
        $first = $this->pay($loan, '0001', '2026-03-02');
        $second = $this->pay($loan, '0021', '2026-03-03');

        $this->loans->updatePaymentDetails($second, ['receipt_no' => '0002', 'remarks' => 'typo fixed']);
        $this->assertSame('0002', $second->refresh()->receipt_no);
        $this->assertSame('11600.00', $second->balance_after);

        $this->expectError('receipt_no', fn () => $this->loans->updatePaymentDetails($second, ['receipt_no' => '0001']));
        $this->assertSame('0001', $first->refresh()->receipt_no);
    }

    public function test_printed_receipt_matches_the_ledger(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '0001', '2026-03-02');
        $payment = $this->pay($loan, '0002', '2026-03-03', 1250.50);

        $receipt = $this->get(route('print.receipts.payment', $payment))->assertOk();
        $receipt->assertSee('Acknowledgment Receipt')
            ->assertSee('0002')
            ->assertSee('One Thousand Two Hundred Fifty Pesos and 50/100')
            ->assertSee('₱11,800.00')   // balance before
            ->assertSee('₱10,549.50')   // balance after
            ->assertSee("Borrower's copy")
            ->assertSee('Office copy')
            ->assertDontSee('Official Receipt');

        // The loan ledger shows the same receipt number and the same balance.
        $this->get(route('print.loan-statement', $loan))->assertOk()->assertSeeInOrder(['0002', '1,250.50', '10,549.50']);
    }

    public function test_savings_receipt_and_withdrawal_slip(): void
    {
        $savings = app(SavingsService::class);
        $account = $savings->open(Borrower::create(['area_id' => $this->north->id, 'first_name' => 'Ana', 'last_name' => 'Reyes']), '2026-03-01');
        $deposit = $savings->deposit($account, ['amount' => 500, 'transaction_date' => '2026-03-02', 'reference_no' => '0007']);
        $withdrawal = $savings->withdraw($account, ['amount' => 200, 'transaction_date' => '2026-03-03', 'reference_no' => 'V-01']);

        $this->get(route('print.receipts.savings', $deposit))->assertOk()->assertSee('0007')->assertSee('₱500.00')->assertSee('Savings balance');
        $this->get(route('print.receipts.savings', $withdrawal))->assertOk()->assertSee('Withdrawal Slip')->assertSee('₱500.00')->assertSee('₱300.00');
        $this->get(route('print.savings-statement', $account))->assertOk()->assertSeeInOrder(['0007', 'V-01', '300.00']);
    }

    public function test_receipt_register_flags_missing_numbers(): void
    {
        $loan = $this->loan();
        $this->pay($loan, '0001', '2026-03-02');
        $this->pay($loan, '0002', '2026-03-03');
        $this->pay($loan, '0005', '2026-03-04');

        $this->get(route('print.reports.receipt-register', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertSeeInOrder(['0001', '0002', 'Missing 0003 to 0004', '0005'])
            ->assertSee('2 missing', false);
    }

    public function test_staff_cannot_print_another_areas_receipt(): void
    {
        $payment = $this->pay($this->loan(), '0001');
        $this->actingAs(User::create(['name' => 'S', 'email' => 's@test', 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $this->south->id]));

        $this->get(route('print.receipts.payment', $payment))->assertNotFound();
    }

    public function test_amount_in_words(): void
    {
        $this->assertSame('One Thousand Two Hundred Fifty Pesos and 50/100', AmountInWords::pesos(1250.5));
        $this->assertSame('One Peso Only', AmountInWords::pesos(1));
        $this->assertSame('Two Hundred Pesos Only', AmountInWords::pesos('200.00'));
    }

    public function test_panel_requires_receipt_and_only_latest_payment_is_deletable(): void
    {
        $loan = $this->loan();

        Livewire::test(ViewLoan::class, ['record' => $loan->getRouteKey()])
            ->callAction('recordPayment', ['amount' => 200, 'payment_date' => '2026-03-02'])
            ->assertHasActionErrors(['receipt_no' => 'required']);

        Livewire::test(ViewLoan::class, ['record' => $loan->getRouteKey()])
            ->callAction('recordPayment', ['amount' => 200, 'payment_date' => '2026-03-02', 'receipt_no' => '0001'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $first = $loan->payments()->first();
        $second = $this->pay($loan, '0002', '2026-03-03');

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $loan, 'pageClass' => ViewLoan::class])
            ->assertTableActionHidden('delete', $first)
            ->assertTableActionVisible('delete', $second)
            ->callTableAction('editDetails', $first, ['receipt_no' => '0003'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('0003', $first->refresh()->receipt_no);
    }
}
