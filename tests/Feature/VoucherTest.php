<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Loans\Pages\CreateLoan;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private Borrower $borrower;

    private LoanPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['lending.voucher.start' => 152, 'lending.voucher.approved_by' => 'Owner Name']);

        $this->area = Area::create(['name' => 'North', 'code' => 'N']);
        $this->borrower = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Editha', 'last_name' => 'Lopez', 'address' => 'Sta. Lucia']);

        // Charges like the Client's sample voucher for a ₱15,000 loan.
        $this->plan = LoanPlan::create([
            'name' => 'Daily 3 months', 'interest_rate' => 17.76, 'rate_basis' => 'per_term', 'interest_method' => 'flat',
            'payment_frequency' => 'daily', 'term' => 66, 'term_unit' => 'days', 'required_savings' => 28,
            'charges' => [
                ['name' => 'Unearned service fee', 'type' => 'percent', 'value' => 11],
                ['name' => 'Notarial allocation', 'type' => 'fixed', 'value' => 1170],
                ['name' => 'Risk management allocation', 'type' => 'fixed', 'value' => 75],
                ['name' => 'PAF', 'type' => 'fixed', 'value' => 120],
                ['name' => 'Documentary stamps', 'type' => 'fixed', 'value' => 390],
            ],
        ]);

        $this->actingAs(User::create(['name' => 'Staff One', 'email' => 's@test', 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $this->area->id]));
    }

    private function loan(float $amount = 15000): Loan
    {
        return app(LoanService::class)->create([
            'borrower_id' => $this->borrower->id, 'loan_plan_id' => $this->plan->id, 'principal' => $amount, 'start_date' => '2026-10-06',
        ]);
    }

    public function test_plan_charges_are_deducted_and_voucher_is_numbered(): void
    {
        $loan = $this->loan();

        $this->assertSame('000152', $loan->voucher_no);
        $this->assertSame('3405.00', $loan->total_charges);      // 1,650 + 1,170 + 75 + 120 + 390
        $this->assertSame('11595.00', $loan->net_proceeds);      // cash actually released
        $this->assertSame('2664.00', $loan->total_interest);
        $this->assertSame('17664.00', $loan->total_payable);
        $this->assertSame('28.00', $loan->required_savings);
        $this->assertEquals(['name' => 'Unearned service fee', 'amount' => 1650], $loan->charges[0]);
        $this->assertSame('cash', $loan->release_method);

        $this->assertSame('000153', $this->loan(5000)->voucher_no);
    }

    public function test_voucher_prints_balanced_entries(): void
    {
        $loan = $this->loan();

        $this->get(route('print.loan-voucher', $loan))
            ->assertOk()
            ->assertSee('Loan Release Voucher')
            ->assertSee('000152')
            ->assertSee('LOPEZ, EDITHA')
            ->assertSeeInOrder(['Loan receivable', '17,664.00', 'Cash on hand', '11,595.00', 'Unearned interest income', '2,664.00', 'Unearned service fee', '1,650.00'])
            ->assertSeeInOrder(['Total', '₱17,664.00', '₱17,664.00'])   // DR = CR
            ->assertSee('Eleven Thousand Five Hundred Ninety Five Pesos Only')
            ->assertSee('₱28.00 per collection')
            ->assertSee('Staff One')
            ->assertSee('Owner Name');
    }

    public function test_charges_cannot_exceed_the_loan_amount(): void
    {
        $this->expectException(ValidationException::class);
        $this->loan(1000); // ₱110 (11%) + ₱1,755 fixed charges is more than the loan
    }

    public function test_owner_custom_terms_with_custom_charges_and_bank_release(): void
    {
        $this->actingAs(User::create(['name' => 'Owner', 'email' => 'o@test', 'password' => 'x', 'role' => UserRole::Admin]));

        $loan = app(LoanService::class)->create([
            'borrower_id' => $this->borrower->id, 'principal' => 10000, 'interest_rate' => 20, 'rate_basis' => 'per_term',
            'interest_method' => 'flat', 'payment_frequency' => 'daily', 'term' => 60, 'term_unit' => 'days', 'start_date' => '2026-10-06',
            'release_method' => 'bank', 'release_reference' => 'BDO-123',
            'charge_rules' => [['name' => 'Processing fee', 'type' => 'fixed', 'value' => 500]],
        ]);

        $this->assertSame('9500.00', $loan->net_proceeds);
        $this->get(route('print.loan-voucher', $loan))->assertOk()->assertSee('Cash in bank')->assertSee('BDO-123');
    }

    public function test_create_form_shows_net_proceeds_and_offers_voucher(): void
    {
        Livewire::test(CreateLoan::class)
            ->fillForm(['borrower_id' => $this->borrower->id, 'loan_plan_id' => $this->plan->id, 'principal' => 15000, 'start_date' => '2026-10-06'])
            ->assertSee('11,595.00')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified();
    }

    public function test_staff_cannot_print_another_areas_voucher(): void
    {
        $loan = $this->loan();
        $south = Area::create(['name' => 'South', 'code' => 'S']);
        $this->actingAs(User::create(['name' => 'S2', 'email' => 's2@test', 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $south->id]));

        $this->get(route('print.loan-voucher', $loan))->assertNotFound();
    }
}
