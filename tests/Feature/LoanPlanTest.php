<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\LoanPlans\LoanPlanResource;
use App\Filament\Resources\LoanPlans\Pages\ManageLoanPlans;
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

class LoanPlanTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private Borrower $borrower;

    private LoanPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'North', 'code' => 'N']);
        $this->borrower = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        $this->plan = LoanPlan::create([
            'name' => 'Daily 60 days', 'interest_rate' => 20, 'rate_basis' => 'per_term', 'interest_method' => 'flat',
            'payment_frequency' => 'daily', 'term' => 60, 'term_unit' => 'days', 'min_amount' => 1000, 'max_amount' => 50000,
        ]);
    }

    private function actingAsStaff(): void
    {
        $this->actingAs(User::create(['name' => 'Staff', 'email' => 's@test', 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $this->area->id]));
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::create(['name' => 'Owner', 'email' => 'o@test', 'password' => 'x', 'role' => UserRole::Admin]));
    }

    private function loanData(array $overrides = []): array
    {
        return [
            'borrower_id' => $this->borrower->id,
            'principal' => 10000,
            'start_date' => '2026-01-01',
            ...$overrides,
        ];
    }

    public function test_staff_loan_uses_plan_terms_even_if_tampered(): void
    {
        $this->actingAsStaff();

        $loan = app(LoanService::class)->create($this->loanData([
            'loan_plan_id' => $this->plan->id,
            'interest_rate' => 0,          // tampered
            'term' => 365,                 // tampered
            'rate_basis' => 'per_term', 'interest_method' => 'flat', 'payment_frequency' => 'daily', 'term_unit' => 'days',
        ]));

        $this->assertSame('20.0000', $loan->interest_rate);
        $this->assertSame(60, $loan->term);
        $this->assertSame('12000.00', $loan->total_payable);
        $this->assertSame($this->plan->id, $loan->loan_plan_id);
    }

    public function test_staff_must_choose_a_plan(): void
    {
        $this->actingAsStaff();

        $this->expectException(ValidationException::class);
        app(LoanService::class)->create($this->loanData([
            'interest_rate' => 1, 'rate_basis' => 'per_term', 'interest_method' => 'flat',
            'payment_frequency' => 'daily', 'term' => 60, 'term_unit' => 'days',
        ]));
    }

    public function test_owner_can_enter_custom_terms(): void
    {
        $this->actingAsAdmin();

        $loan = app(LoanService::class)->create($this->loanData([
            'interest_rate' => 15, 'rate_basis' => 'per_term', 'interest_method' => 'flat',
            'payment_frequency' => 'daily', 'term' => 45, 'term_unit' => 'days',
        ]));

        $this->assertNull($loan->loan_plan_id);
        $this->assertSame('11500.00', $loan->total_payable);
    }

    public function test_inactive_plan_and_amount_limits_are_enforced(): void
    {
        $this->actingAsStaff();
        $service = app(LoanService::class);

        try {
            $service->create($this->loanData(['loan_plan_id' => $this->plan->id, 'principal' => 500]));
            $this->fail('Below minimum accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('principal', $e->errors());
        }

        $this->plan->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        $service->create($this->loanData(['loan_plan_id' => $this->plan->id]));
    }

    public function test_staff_creates_loan_through_form_with_plan(): void
    {
        $this->actingAsStaff();

        Livewire::test(CreateLoan::class)
            ->assertFormSet(['loan_plan_id' => $this->plan->id, 'interest_rate' => '20.0000', 'term' => 60])
            ->assertFormFieldIsDisabled('interest_rate')
            ->assertFormFieldIsDisabled('term')
            ->fillForm(['borrower_id' => $this->borrower->id, 'principal' => 10000, 'start_date' => '2026-01-01'])
            ->assertSee('12,000.00')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('12000.00', Loan::firstOrFail()->total_payable);
    }

    public function test_owner_form_allows_custom_terms(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateLoan::class)
            ->fillForm(['loan_plan_id' => null])
            ->assertFormFieldIsEnabled('interest_rate')
            ->fillForm([
                'borrower_id' => $this->borrower->id, 'principal' => 10000, 'interest_rate' => 15,
                'rate_basis' => 'per_term', 'interest_method' => 'flat', 'payment_frequency' => 'daily',
                'term' => 45, 'term_unit' => 'days', 'start_date' => '2026-01-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('11500.00', Loan::firstOrFail()->total_payable);
    }

    public function test_plan_page_is_owner_only(): void
    {
        $this->actingAsStaff();
        $this->get(LoanPlanResource::getUrl('index'))->assertForbidden();
    }

    public function test_owner_manages_plans(): void
    {
        $this->actingAsAdmin();

        $this->get(LoanPlanResource::getUrl('index'))->assertOk()->assertSee('Daily 60 days')->assertSee('₱200.00 × 60 daily');

        Livewire::test(ManageLoanPlans::class)
            ->callAction('create', [
                'name' => 'Weekly 3 months', 'interest_rate' => 15, 'rate_basis' => 'per_term', 'interest_method' => 'flat',
                'payment_frequency' => 'weekly', 'term' => 3, 'term_unit' => 'months', 'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('loan_plans', ['name' => 'Weekly 3 months', 'payment_frequency' => 'weekly']);
    }
}
