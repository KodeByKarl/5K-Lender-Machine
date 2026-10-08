<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\SavingsAccounts\Pages\CreateSavingsAccount;
use App\Filament\Resources\SavingsAccounts\Pages\ViewSavingsAccount;
use App\Filament\Resources\SavingsAccounts\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\SavingsAccounts\SavingsAccountResource;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SavingsTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private SavingsService $savings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->area = Area::create(['name' => 'North', 'code' => 'N']);
        $this->savings = app(SavingsService::class);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'a@test', 'password' => 'x', 'role' => UserRole::Admin]));
    }

    private function account(): SavingsAccount
    {
        $borrower = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Juan', 'last_name' => 'Cruz']);

        return $this->savings->open($borrower, '2026-01-01');
    }

    public function test_deposits_and_withdrawals_keep_running_balance(): void
    {
        $account = $this->account();

        $this->savings->deposit($account, ['amount' => 1000, 'transaction_date' => '2026-01-05', 'reference_no' => '0001']);
        $this->savings->deposit($account, ['amount' => 500, 'transaction_date' => '2026-01-10', 'reference_no' => '0002']);
        $w = $this->savings->withdraw($account, ['amount' => 300, 'transaction_date' => '2026-01-12']);

        $this->assertSame('1200.00', $w->running_balance);
        $this->assertSame('1200.00', $account->refresh()->balance);
        $this->assertSame('SAV-00001', $account->account_no);
        $this->assertSame($this->area->id, $account->area_id);
    }

    public function test_withdrawal_cannot_exceed_balance(): void
    {
        $account = $this->account();
        $this->savings->deposit($account, ['amount' => 100, 'transaction_date' => '2026-01-05', 'reference_no' => '0001']);

        $this->expectException(ValidationException::class);
        $this->savings->withdraw($account, ['amount' => 100.01, 'transaction_date' => '2026-01-06']);
    }

    public function test_deposit_requires_a_receipt_number(): void
    {
        $account = $this->account();

        try {
            $this->savings->deposit($account, ['amount' => 100, 'transaction_date' => '2026-01-05']);
            $this->fail('Deposit without receipt number accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reference_no', $e->errors());
        }
    }

    public function test_transactions_cannot_be_dated_before_the_latest_one(): void
    {
        // Back-dating would change the running balance printed on receipts already given.
        $account = $this->account();
        $this->savings->deposit($account, ['amount' => 1000, 'transaction_date' => '2026-01-10', 'reference_no' => '0001']);

        try {
            $this->savings->deposit($account, ['amount' => 200, 'transaction_date' => '2026-01-05', 'reference_no' => '0002']);
            $this->fail('Back-dated deposit accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transaction_date', $e->errors());
        }

        $this->savings->deposit($account, ['amount' => 200, 'transaction_date' => '2026-01-10', 'reference_no' => '0002']); // same day is fine
        $this->assertSame('1200.00', $account->refresh()->balance);
    }

    public function test_only_the_latest_transaction_can_be_deleted(): void
    {
        $account = $this->account();
        $first = $this->savings->deposit($account, ['amount' => 1000, 'transaction_date' => '2026-01-05', 'reference_no' => '0001']);
        $last = $this->savings->withdraw($account, ['amount' => 800, 'transaction_date' => '2026-01-06']);

        try {
            $this->savings->delete($first);
            $this->fail('Earlier transaction deleted.');
        } catch (ValidationException) {
            $this->assertSame(2, $account->transactions()->count());
        }

        $this->savings->delete($last);
        $this->assertSame('1000.00', $account->refresh()->balance);
    }

    public function test_one_account_per_borrower(): void
    {
        $account = $this->account();

        $this->expectException(ValidationException::class);
        $this->savings->open($account->borrower);
    }

    public function test_panel_pages_actions_and_statement(): void
    {
        $borrower = Borrower::create(['area_id' => $this->area->id, 'first_name' => 'Ana', 'last_name' => 'Reyes']);

        $this->get(SavingsAccountResource::getUrl('index'))->assertOk();
        $this->get(SavingsAccountResource::getUrl('create', ['borrower' => $borrower->id]))->assertOk();

        Livewire::test(CreateSavingsAccount::class)
            ->fillForm(['borrower_id' => $borrower->id, 'opened_at' => today()->toDateString()])
            ->call('create')
            ->assertHasNoFormErrors();

        $account = SavingsAccount::firstOrFail();

        Livewire::test(ViewSavingsAccount::class, ['record' => $account->getRouteKey()])
            ->callAction('deposit', ['amount' => 750, 'transaction_date' => today()->toDateString(), 'reference_no' => 'OR-1'])
            ->assertHasNoActionErrors()
            ->callAction('withdrawal', ['amount' => 1000, 'transaction_date' => today()->toDateString()])
            ->assertHasActionErrors(['amount']);

        $this->assertSame('750.00', $account->refresh()->balance);

        $this->get(SavingsAccountResource::getUrl('view', ['record' => $account]))->assertOk()->assertSee($account->account_no)->assertSee('Deposit');

        Livewire::test(TransactionsRelationManager::class, ['ownerRecord' => $account, 'pageClass' => ViewSavingsAccount::class])
            ->assertOk()
            ->assertSee('OR-1');

        $this->get(route('print.savings-statement', $account))
            ->assertOk()
            ->assertSee('Savings Ledger Statement')
            ->assertSee('Reyes')
            ->assertSee('₱750.00');
    }

    public function test_staff_cannot_print_other_area_statement(): void
    {
        $account = $this->account();
        $south = Area::create(['name' => 'South', 'code' => 'S']);
        $this->actingAs(User::create(['name' => 'Staff', 'email' => 's@test', 'password' => 'x', 'role' => UserRole::Staff, 'area_id' => $south->id]));

        $this->get(route('print.savings-statement', $account))->assertNotFound();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $account = $this->account();
        auth()->logout();

        $this->get(route('print.savings-statement', $account))->assertRedirect(route('filament.admin.auth.login'));
    }
}
