<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports;
use App\Filament\Widgets\CollectionsChart;
use App\Filament\Widgets\LendingStats;
use App\Filament\Widgets\OverdueLoans;
use App\Filament\Widgets\RecentPayments;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReportService;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Area $north;

    private Area $south;

    private Loan $northLoan;

    private Loan $southLoan;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-10 09:00');

        $this->north = Area::create(['name' => 'North', 'code' => 'N']);
        $this->south = Area::create(['name' => 'South', 'code' => 'S']);
        $this->actingAs(User::create(['name' => 'Owner', 'email' => 'o@test', 'password' => 'x', 'role' => UserRole::Admin]));

        $this->northLoan = $this->loan($this->north, 'Cruz', '2026-03-01');  // 9 days in, ₱200/day
        $this->southLoan = $this->loan($this->south, 'Santos', '2026-03-05'); // 5 days in

        $loans = app(LoanService::class);
        $loans->recordPayment($this->northLoan, ['amount' => 1000, 'payment_date' => '2026-03-06', 'receipt_no' => 'OR-1']); // 5 days paid → 4 days behind
        $loans->recordPayment($this->southLoan, ['amount' => 1000, 'payment_date' => '2026-03-10', 'receipt_no' => 'OR-2']); // fully up to date
        $loans->refreshStatuses();

        $savings = app(SavingsService::class);
        $account = $savings->open($this->northLoan->borrower, '2026-03-01');
        $savings->deposit($account, ['amount' => 500, 'transaction_date' => '2026-03-02', 'reference_no' => 'OR-3']);
    }

    private function loan(Area $area, string $name, string $start): Loan
    {
        $borrower = Borrower::create(['area_id' => $area->id, 'first_name' => 'Juan', 'last_name' => $name]);

        return app(LoanService::class)->create([
            'borrower_id' => $borrower->id, 'principal' => 10000, 'interest_rate' => 20, 'rate_basis' => 'per_term',
            'interest_method' => 'flat', 'payment_frequency' => 'daily', 'term' => 60, 'term_unit' => 'days', 'start_date' => $start,
        ]);
    }

    public function test_summary_figures(): void
    {
        $reports = app(ReportService::class);
        $all = $reports->summary(null);

        $this->assertSame(2, $all['borrowers']);
        $this->assertSame(2, $all['active_loans']);
        $this->assertEqualsWithDelta(22000, $all['outstanding'], 0.001);
        $this->assertEqualsWithDelta(2000, $all['collected'], 0.001);
        $this->assertEqualsWithDelta(1000, $all['collected_today'], 0.001);
        $this->assertSame(1, $all['overdue_loans']);
        $this->assertEqualsWithDelta(600, $all['past_due'], 0.001);   // North: days 6–8 unpaid (3 × ₱200)
        $this->assertEqualsWithDelta(200, $all['due_today'], 0.001);  // North day 9; South day 5 already paid
        $this->assertEqualsWithDelta(500, $all['savings'], 0.001);

        $south = $reports->summary($this->south->id);
        $this->assertSame(1, $south['borrowers']);
        $this->assertSame(0, $south['overdue_loans']);
    }

    public function test_collection_sheet_and_overdue_lists(): void
    {
        $reports = app(ReportService::class);

        // Both loans stay on the route: North is behind; South already paid today and shows as paid.
        $sheet = $reports->collectionSheet(null, today())->keyBy('id');
        $this->assertCount(2, $sheet);

        $north = $sheet[$this->northLoan->id];
        $this->assertEqualsWithDelta(200, $north->due_today, 0.001);
        $this->assertEqualsWithDelta(600, $north->arrears, 0.001);
        $this->assertEqualsWithDelta(800, $north->to_collect, 0.001);

        $south = $sheet[$this->southLoan->id];
        $this->assertEqualsWithDelta(200, $south->due_today, 0.001);
        $this->assertEqualsWithDelta(0, $south->to_collect, 0.001);
        $this->assertEqualsWithDelta(1000, $south->paid_today, 0.001);

        $overdue = $reports->overdueLoans(null);
        $this->assertCount(1, $overdue);
        $this->assertSame(3, $overdue[0]->days_late);
        $this->assertSame(3, $overdue[0]->missed_installments);
    }

    public function test_staff_reports_are_limited_to_their_area(): void
    {
        $this->actingAs(User::create(['name' => 'S', 'email' => 's@test', 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $this->south->id]));
        $reports = app(ReportService::class);

        // Even when asking for another Area, staff get their own.
        $this->assertSame($this->south->id, $reports->resolveArea($this->north->id));
        $this->assertSame(1, $reports->summary(null)['borrowers']);

        $this->get(route('print.reports.outstanding', ['area' => $this->north->id]))
            ->assertOk()->assertSee('Santos')->assertDontSee('Cruz');
        $this->get(route('print.loan-statement', $this->northLoan))->assertNotFound();
    }

    public function test_dashboard_and_widgets_render(): void
    {
        $this->get(Dashboard::getUrl())->assertOk();

        Livewire::test(LendingStats::class)->assertOk()->assertSee('₱1,000.00')->assertSee('₱22,000.00');
        Livewire::test(LendingStats::class, ['pageFilters' => ['area_id' => $this->south->id]])->assertOk()->assertSee('₱11,000.00');
        Livewire::test(CollectionsChart::class)->assertOk();
        Livewire::test(OverdueLoans::class)->assertOk()->assertCanSeeTableRecords([$this->northLoan])->assertCanNotSeeTableRecords([$this->southLoan]);
        Livewire::test(RecentPayments::class)->assertOk()->assertCanSeeTableRecords(\App\Models\Payment::all());
    }

    public function test_reports_page_and_every_report_render(): void
    {
        $this->get(Reports::getUrl())->assertOk()->assertSee('Daily collection sheet');

        Livewire::test(Reports::class)
            ->fillForm(['area_id' => $this->north->id])
            ->assertSee(route('print.reports.outstanding', ['area' => $this->north->id]), escape: false);

        $this->get(route('print.reports.collection-sheet'))->assertOk()->assertSee('Daily Collection Sheet')->assertSee('Cruz')->assertSee('800.00');
        $this->get(route('print.reports.payments', ['from' => '2026-03-01', 'to' => '2026-03-31']))->assertOk()->assertSee('OR-1')->assertSee('OR-2')->assertSee('2,000.00');
        $this->get(route('print.reports.outstanding'))->assertOk()->assertSee('Grand total')->assertSee('22,000.00');
        $this->get(route('print.reports.overdue'))->assertOk()->assertSee('Cruz')->assertDontSee('Santos');
        $this->get(route('print.reports.savings'))->assertOk()->assertSee('SAV-00001')->assertSee('500.00');
        $this->get(route('print.reports.borrower', $this->northLoan->borrower))->assertOk()->assertSee($this->northLoan->loan_no)->assertSee('OR-1');
        $this->get(route('print.loan-statement', $this->northLoan))->assertOk()->assertSee('Repayment schedule')->assertSee('Late');

        $this->assertSame(LoanStatus::Overdue, $this->northLoan->refresh()->status);
    }
}
