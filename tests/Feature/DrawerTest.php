<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Borrowers\Pages\ListBorrowers;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\SavingsAccounts\Pages\ListSavingsAccounts;
use App\Filament\Widgets\OverdueLoans;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use App\Services\SavingsService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Viewing a borrower, loan, or savings account opens a drawer on the right, not a new page. */
class DrawerTest extends TestCase
{
    use RefreshDatabase;

    private Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $area = Area::create(['name' => 'North', 'code' => 'N']);
        $this->actingAs(User::create(['name' => 'Owner', 'email' => 'o@test', 'password' => 'x', 'role' => UserRole::Admin]));

        $borrower = Borrower::create(['area_id' => $area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $this->loan = app(LoanService::class)->create([
            'borrower_id' => $borrower->id, 'principal' => 10000, 'interest_rate' => 20, 'rate_basis' => 'per_term',
            'interest_method' => 'flat', 'payment_frequency' => 'daily', 'term' => 60, 'term_unit' => 'days', 'start_date' => today()->subDays(3),
        ]);
        app(LoanService::class)->recordPayment($this->loan, ['amount' => 200, 'payment_date' => today()->subDays(2), 'receipt_no' => '0001']);
    }

    public function test_rows_open_a_drawer_instead_of_a_page(): void
    {
        $table = Livewire::test(ListLoans::class)->instance()->getTable();

        $this->assertNull($table->getRecordUrl($this->loan));
        $this->assertSame('view', $table->getRecordAction($this->loan));
        $this->assertTrue($table->getAction('view')->isModalSlideOver());
    }

    public function test_loan_drawer_shows_details_and_records_a_payment(): void
    {
        Livewire::test(ListLoans::class)
            ->mountTableAction('view', $this->loan)
            ->assertMountedActionModalSee($this->loan->loan_no.' · Cruz, Juan')
            ->assertMountedActionModalSee('Remaining balance')
            ->assertMountedActionModalSee('Payment history')
            ->assertMountedActionModalSee('Repayment schedule');

        Livewire::test(ListLoans::class)
            ->callAction([
                TestAction::make('view')->table($this->loan),
                TestAction::make('recordPayment'),
            ], ['receipt_no' => '0002', 'payment_date' => today()->toDateString(), 'amount' => 200])
            ->assertHasNoActionErrors();

        $this->assertSame('11600.00', $this->loan->refresh()->balance);
        $this->assertSame('0002', $this->loan->payments()->latest('id')->value('receipt_no'));
    }

    public function test_borrower_drawer(): void
    {
        Livewire::test(ListBorrowers::class)
            ->mountTableAction('view', $this->loan->borrower)
            ->assertMountedActionModalSee('Cruz, Juan')
            ->assertMountedActionModalSee('Loan history')
            ->assertMountedActionModalSee('New loan');
    }

    public function test_savings_drawer_deposit(): void
    {
        $savings = app(SavingsService::class);
        $account = $savings->open($this->loan->borrower, today()->subDays(3)->toDateString());

        Livewire::test(ListSavingsAccounts::class)
            ->mountTableAction('view', $account)
            ->assertMountedActionModalSee($account->account_no)
            ->assertMountedActionModalSee('Available balance');

        Livewire::test(ListSavingsAccounts::class)
            ->callAction([
                TestAction::make('view')->table($account),
                TestAction::make('deposit'),
            ], ['reference_no' => '0100', 'transaction_date' => today()->toDateString(), 'amount' => 150])
            ->assertHasNoActionErrors();

        $this->assertSame('150.00', $account->refresh()->balance);
    }

    public function test_dashboard_overdue_rows_open_the_loan_drawer(): void
    {
        app(LoanService::class)->refreshStatuses();

        Livewire::test(OverdueLoans::class)
            ->mountTableAction('view', $this->loan->refresh())
            ->assertMountedActionModalSee('Remaining balance');
    }
}
