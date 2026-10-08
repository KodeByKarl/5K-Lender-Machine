<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Borrowers\BorrowerResource;
use App\Filament\Resources\Borrowers\Pages\CreateBorrower;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Pages\CreateLoan;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Filament\Resources\Loans\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\Loans\RelationManagers\PaymentsRelationManager;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LendingPanelTest extends TestCase
{
    use RefreshDatabase;

    private Area $north;

    private Area $south;

    protected function setUp(): void
    {
        parent::setUp();

        $this->north = Area::create(['name' => 'North', 'code' => 'N']);
        $this->south = Area::create(['name' => 'South', 'code' => 'S']);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'a@test', 'password' => 'x', 'role' => UserRole::Admin]);
    }

    private function staff(Area $area): User
    {
        return User::create(['name' => 'Staff', 'email' => "s{$area->id}@test", 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $area->id]);
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->admin());
        $borrower = Borrower::create(['area_id' => $this->north->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);

        $this->get(BorrowerResource::getUrl('index'))->assertOk()->assertSee('Cruz');
        $this->get(BorrowerResource::getUrl('create'))->assertOk();
        $this->get(BorrowerResource::getUrl('view', ['record' => $borrower]))->assertOk();
        $this->get(LoanResource::getUrl('index'))->assertOk();
        $this->get(LoanResource::getUrl('create', ['borrower' => $borrower->id]))->assertOk();
        $this->get('/admin')->assertOk();
    }

    public function test_staff_creates_borrower_in_own_area(): void
    {
        $this->actingAs($this->staff($this->south));

        Livewire::test(CreateBorrower::class)
            ->fillForm(['first_name' => 'Ana', 'last_name' => 'Reyes', 'contact_no' => '0917'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->south->id, Borrower::firstWhere('last_name', 'Reyes')->area_id);
    }

    public function test_create_loan_view_and_record_payment(): void
    {
        $this->actingAs($this->admin());
        $borrower = Borrower::create(['area_id' => $this->north->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);

        Livewire::test(CreateLoan::class)
            ->fillForm([
                'borrower_id' => $borrower->id,
                'principal' => 10000,
                'interest_rate' => 5,
                'rate_basis' => 'per_month',
                'interest_method' => 'flat',
                'payment_frequency' => 'monthly',
                'term' => 3, 'term_unit' => 'months',
                'start_date' => today()->toDateString(),
            ])
            ->assertSee('11,500.00') // live preview
            ->call('create')
            ->assertHasNoFormErrors();

        $loan = Loan::firstOrFail();
        $this->assertSame('11500.00', $loan->balance);
        $this->assertSame($this->north->id, $loan->area_id);

        $this->get(LoanResource::getUrl('view', ['record' => $loan]))->assertOk()->assertSee('Repayment schedule');

        Livewire::test(ViewLoan::class, ['record' => $loan->getRouteKey()])
            ->callAction('recordPayment', ['amount' => 3833.33, 'payment_date' => today()->toDateString(), 'receipt_no' => '0101'])
            ->assertHasNoActionErrors();

        $this->assertSame('7666.67', $loan->refresh()->balance);

        Livewire::test(InstallmentsRelationManager::class, ['ownerRecord' => $loan, 'pageClass' => ViewLoan::class])
            ->assertOk()->assertSee('Paid');
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $loan, 'pageClass' => ViewLoan::class])
            ->assertOk()->assertSee('3,833.33');
    }

    public function test_overpayment_shows_validation_error(): void
    {
        $this->actingAs($this->admin());
        $borrower = Borrower::create(['area_id' => $this->north->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $loan = app(\App\Services\LoanService::class)->create([
            'borrower_id' => $borrower->id, 'principal' => 1000, 'interest_rate' => 0, 'rate_basis' => 'per_month',
            'interest_method' => 'flat', 'payment_frequency' => 'monthly', 'term' => 1, 'term_unit' => 'months', 'start_date' => today(),
        ]);

        Livewire::test(ViewLoan::class, ['record' => $loan->getRouteKey()])
            ->callAction('recordPayment', ['amount' => 5000, 'payment_date' => today()->toDateString(), 'receipt_no' => '0102'])
            ->assertHasActionErrors(['amount']);

        $this->assertSame(LoanStatus::Active, $loan->refresh()->status);
    }

    public function test_staff_cannot_open_other_area_records(): void
    {
        $other = Borrower::create(['area_id' => $this->north->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $this->actingAs($this->staff($this->south));

        $this->get(BorrowerResource::getUrl('index'))->assertOk()->assertDontSee('Cruz');
        $this->get(BorrowerResource::getUrl('view', ['record' => $other]))->assertNotFound();
    }
}
