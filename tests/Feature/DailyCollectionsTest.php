<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\DailyCollections;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $staff;

    private LoanPlan $plan;

    private Loan $loan1;

    private Loan $loan2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'Candaba', 'code' => 'CND']);
        $this->staff = User::create([
            'name' => 'Collector One',
            'email' => 'col1@test.com',
            'password' => 'secret',
            'role' => UserRole::Staff,
            'area_id' => $this->area->id,
        ]);

        $this->plan = LoanPlan::create([
            'name' => 'Daily 30 days',
            'interest_rate' => 10,
            'rate_basis' => 'per_term',
            'interest_method' => 'flat',
            'payment_frequency' => 'daily',
            'term' => 30,
            'term_unit' => 'days',
        ]);

        $b1 = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Editha', 'last_name' => 'Lopez', 'address' => 'Sta. Lucia']);
        $b2 = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'address' => 'San Agustin']);

        $this->actingAs($this->staff);

        $this->loan1 = app(LoanService::class)->create([
            'borrower_id' => $b1->id,
            'loan_plan_id' => $this->plan->id,
            'principal' => 10000,
            'start_date' => today()->subDays(5)->toDateString(),
        ]);

        $this->loan2 = app(LoanService::class)->create([
            'borrower_id' => $b2->id,
            'loan_plan_id' => $this->plan->id,
            'principal' => 15000,
            'start_date' => today()->subDays(5)->toDateString(),
        ]);
    }

    public function test_page_renders_and_loads_due_loans(): void
    {
        Livewire::test(DailyCollections::class)
            ->assertOk()
            ->assertSee('Daily Collection Entry')
            ->assertSee('Lopez, Editha')
            ->assertSee('Dela Cruz, Juan')
            ->assertSee($this->loan1->loan_no)
            ->assertSee($this->loan2->loan_no);
    }

    public function test_fill_all_with_due_and_auto_numbering(): void
    {
        Livewire::test(DailyCollections::class)
            ->call('fillAllWithDue')
            ->set('start_receipt_no', '1001')
            ->call('autoNumberReceipts')
            ->assertNotified('Assigned 2 sequential receipt numbers!');
    }

    public function test_posting_batch_collections_records_payments(): void
    {
        $installment1Due = (float) $this->loan1->installments()->first()->amount_due;
        $installment2Due = (float) $this->loan2->installments()->first()->amount_due;

        Livewire::test(DailyCollections::class)
            ->set("rows.{$this->loan1->id}.amount", (string) $installment1Due)
            ->set("rows.{$this->loan1->id}.receipt_no", 'RCP-001')
            ->set("rows.{$this->loan2->id}.amount", (string) $installment2Due)
            ->set("rows.{$this->loan2->id}.receipt_no", 'RCP-002')
            ->call('postCollections')
            ->assertNotified('Successfully posted 2 collections!');

        $this->loan1->refresh();
        $this->loan2->refresh();

        $this->assertSame(1, $this->loan1->payments()->count());
        $this->assertSame('RCP-001', $this->loan1->payments()->first()->receipt_no);
        $this->assertEquals($installment1Due, (float) $this->loan1->payments()->first()->amount);

        $this->assertSame(1, $this->loan2->payments()->count());
        $this->assertSame('RCP-002', $this->loan2->payments()->first()->receipt_no);
        $this->assertEquals($installment2Due, (float) $this->loan2->payments()->first()->amount);
    }

    public function test_batch_posting_rejects_missing_receipt_number(): void
    {
        Livewire::test(DailyCollections::class)
            ->set("rows.{$this->loan1->id}.amount", '300')
            ->set("rows.{$this->loan1->id}.receipt_no", '')
            ->call('postCollections')
            ->assertNotified('Missing receipt number');

        $this->assertSame(0, $this->loan1->payments()->count());
    }

    public function test_batch_posting_rejects_duplicate_receipt_numbers_in_batch(): void
    {
        Livewire::test(DailyCollections::class)
            ->set("rows.{$this->loan1->id}.amount", '300')
            ->set("rows.{$this->loan1->id}.receipt_no", 'RCP-DUPLICATE')
            ->set("rows.{$this->loan2->id}.amount", '400')
            ->set("rows.{$this->loan2->id}.receipt_no", 'RCP-DUPLICATE')
            ->call('postCollections')
            ->assertNotified('Duplicate receipt number in batch');

        $this->assertSame(0, $this->loan1->payments()->count());
        $this->assertSame(0, $this->loan2->payments()->count());
    }
}
