<?php

namespace App\Filament\Pages;

use App\Enums\LoanStatus;
use App\Models\Area;
use App\Models\Loan;
use App\Services\LoanService;
use App\Services\ReceiptBook;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DailyCollections extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Lending';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Daily Collection Entry';

    protected static ?string $navigationLabel = 'Daily collections';

    protected string $view = 'filament.pages.daily-collections';

    public ?int $area_id = null;

    public string $date = '';

    public ?string $start_receipt_no = '';

    public string $search = '';

    public string $status_filter = 'all'; // 'all' | 'pending' | 'collected'

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $this->area_id = Area::orderBy('name')->value('id');
        } else {
            $this->area_id = $user->area_id;
        }

        $this->date = today()->toDateString();
        $this->loadRows();
    }

    public function updatedAreaId(): void
    {
        if (! auth()->user()->isAdmin()) {
            $this->area_id = auth()->user()->area_id;
        }

        $this->loadRows();
    }

    public function updatedDate(): void
    {
        $this->loadRows();
    }

    public function loadRows(): void
    {
        if (! $this->area_id) {
            $this->rows = [];

            return;
        }

        $date = Carbon::parse($this->date);

        // Fetch loans in the area that have dues on/before this date or have payments today
        $loans = Loan::query()
            ->where('area_id', $this->area_id)
            ->whereDate('start_date', '<=', $date)
            ->where(fn ($q) => $q
                ->where('status', '!=', LoanStatus::Paid)
                ->orWhereHas('payments', fn ($p) => $p->whereDate('payment_date', $date)))
            ->with([
                'borrower',
                'plan',
                'installments' => fn ($q) => $q->whereDate('due_date', '<=', $date)
                    ->where(fn ($q) => $q->whereColumn('amount_paid', '<', 'amount_due')->orWhereDate('due_date', $date)),
                'payments' => fn ($q) => $q->whereDate('payment_date', $date),
            ])
            ->get()
            ->each(function (Loan $loan) use ($date) {
                $remaining = fn ($i) => max(0, (float) $i->amount_due - (float) $i->amount_paid);
                $today = $loan->installments->filter(fn ($i) => $i->due_date->isSameDay($date));

                $loan->due_today = (float) $today->sum(fn ($i) => (float) $i->amount_due);
                $loan->arrears = (float) $loan->installments->filter(fn ($i) => $i->due_date->lt($date))->sum($remaining);
                $loan->to_collect = (float) ($today->sum($remaining) + $loan->arrears);
                $loan->paid_today = (float) $loan->payments->sum(fn ($p) => (float) $p->amount);
            })
            ->filter(fn (Loan $loan) => $loan->due_today > 0 || $loan->arrears > 0 || $loan->paid_today > 0)
            ->sortBy(fn (Loan $loan) => [$loan->borrower->last_name, $loan->borrower->first_name])
            ->values();

        $rows = [];
        foreach ($loans as $loan) {
            $isPaidToday = $loan->paid_today > 0;
            $defaultAmount = $isPaidToday ? '' : ($loan->to_collect > 0 ? (string) $loan->to_collect : ($loan->due_today > 0 ? (string) $loan->due_today : ''));

            $rows[$loan->id] = [
                'loan_id' => $loan->id,
                'loan_no' => $loan->loan_no,
                'borrower_name' => $loan->borrower->full_name,
                'borrower_ref' => $loan->borrower->reference_no,
                'borrower_address' => $loan->borrower->address ?: '—',
                'contact_no' => $loan->borrower->contact_no ?: '—',
                'plan_name' => $loan->plan?->name ?? 'Custom',
                'due_today' => $loan->due_today,
                'arrears' => $loan->arrears,
                'to_collect' => $loan->to_collect,
                'paid_today' => $loan->paid_today,
                'balance' => (float) $loan->balance,
                'required_savings' => (float) ($loan->required_savings ?? 0),
                'amount' => $defaultAmount,
                'receipt_no' => '',
                'remarks' => '',
                'is_already_paid' => $isPaidToday,
            ];
        }

        $this->rows = $rows;
    }

    /** 1-click helper: sets amount to total to collect for all unrecorded accounts. */
    public function fillAllWithDue(): void
    {
        $count = 0;
        foreach ($this->rows as $id => $row) {
            if (! $row['is_already_paid']) {
                $target = $row['to_collect'] > 0 ? $row['to_collect'] : $row['due_today'];
                $this->rows[$id]['amount'] = (string) $target;
                $count++;
            }
        }

        Notification::make()
            ->info()
            ->title("Pre-filled {$count} accounts with due amounts.")
            ->send();
    }

    /** Fills specific row with total due. */
    public function fillRowFull(int $loanId): void
    {
        if (isset($this->rows[$loanId])) {
            $target = $this->rows[$loanId]['to_collect'] > 0 ? $this->rows[$loanId]['to_collect'] : $this->rows[$loanId]['due_today'];
            $this->rows[$loanId]['amount'] = (string) $target;
        }
    }

    /** Fills specific row with installment due today. */
    public function fillRowDueToday(int $loanId): void
    {
        if (isset($this->rows[$loanId])) {
            $this->rows[$loanId]['amount'] = (string) $this->rows[$loanId]['due_today'];
        }
    }

    /** Clears amount and receipt for a specific row (mark as skip/unpaid). */
    public function skipRow(int $loanId): void
    {
        if (isset($this->rows[$loanId])) {
            $this->rows[$loanId]['amount'] = '';
            $this->rows[$loanId]['receipt_no'] = '';
        }
    }

    /** Resets all amount & receipt fields for unrecorded accounts. */
    public function clearAll(): void
    {
        foreach ($this->rows as $id => $row) {
            if (! $row['is_already_paid']) {
                $this->rows[$id]['amount'] = '';
                $this->rows[$id]['receipt_no'] = '';
                $this->rows[$id]['remarks'] = '';
            }
        }

        Notification::make()
            ->info()
            ->title('Cleared all collection entries.')
            ->send();
    }

    /** Automatically numbers receipt booklet series down the rows that have amounts. */
    public function autoNumberReceipts(): void
    {
        $start = trim((string) $this->start_receipt_no);

        if ($start === '') {
            Notification::make()
                ->warning()
                ->title('Starting receipt number required')
                ->body('Enter your booklet starting number (e.g. 1001 or 000501) then click Auto-number.')
                ->send();

            return;
        }

        if (preg_match('/^(.*?)(\d+)$/', $start, $m)) {
            $prefix = $m[1];
            $num = (int) $m[2];
            $len = strlen($m[2]);
        } else {
            $prefix = $start.'-';
            $num = 1;
            $len = 4;
        }

        $assigned = 0;
        foreach ($this->rows as $id => $row) {
            $amount = (float) ($row['amount'] ?? 0);
            if ($amount > 0 && ! $row['is_already_paid']) {
                $formatted = $prefix.str_pad((string) $num, $len, '0', STR_PAD_LEFT);
                $this->rows[$id]['receipt_no'] = $formatted;
                $num++;
                $assigned++;
            }
        }

        if ($assigned > 0) {
            Notification::make()
                ->success()
                ->title("Assigned {$assigned} sequential receipt numbers!")
                ->send();
        } else {
            Notification::make()
                ->warning()
                ->title('No accounts with amounts to number')
                ->body('Make sure accounts have a collection amount entered before auto-numbering.')
                ->send();
        }
    }

    /** Validates and posts all entered collections in one atomic database transaction. */
    public function postCollections(): void
    {
        $collecting = [];

        foreach ($this->rows as $loanId => $r) {
            $amt = (float) ($r['amount'] ?? 0);
            if ($amt > 0 && ! ($r['is_already_paid'] ?? false)) {
                $collecting[$loanId] = $r;
            }
        }

        if (empty($collecting)) {
            Notification::make()
                ->warning()
                ->title('No collections to post')
                ->body('Enter amount and receipt number for at least one client.')
                ->send();

            return;
        }

        // 1. Validate receipt numbers are present
        foreach ($collecting as $r) {
            if (trim((string) $r['receipt_no']) === '') {
                Notification::make()
                    ->danger()
                    ->title('Missing receipt number')
                    ->body("Please enter a receipt number for {$r['borrower_name']}.")
                    ->send();

                return;
            }
        }

        // 2. Validate uniqueness within this batch
        $seen = [];
        foreach ($collecting as $r) {
            $rcp = strtoupper(trim((string) $r['receipt_no']));
            if (isset($seen[$rcp])) {
                Notification::make()
                    ->danger()
                    ->title('Duplicate receipt number in batch')
                    ->body("Receipt #{$rcp} was entered more than once. Each receipt number must be unique.")
                    ->send();

                return;
            }
            $seen[$rcp] = $r['borrower_name'];
        }

        // 3. Pre-validate receipt numbers in database using ReceiptBook
        $receiptBook = app(ReceiptBook::class);
        foreach ($collecting as $r) {
            try {
                $receiptBook->claim($this->area_id, $r['receipt_no']);
            } catch (ValidationException $e) {
                Notification::make()
                    ->danger()
                    ->title('Receipt validation error')
                    ->body("For {$r['borrower_name']}: ".$e->getMessage())
                    ->send();

                return;
            }
        }

        // 4. Save inside a transaction
        try {
            $count = 0;
            $totalAmount = 0.0;

            DB::transaction(function () use ($collecting, &$count, &$totalAmount) {
                $loanService = app(LoanService::class);

                foreach ($collecting as $loanId => $r) {
                    $loan = Loan::findOrFail($loanId);
                    $loanService->recordPayment($loan, [
                        'payment_date' => $this->date,
                        'amount' => (float) $r['amount'],
                        'receipt_no' => $r['receipt_no'],
                        'remarks' => trim((string) ($r['remarks'] ?? '')) ?: null,
                    ]);

                    $count++;
                    $totalAmount += (float) $r['amount'];
                }
            });

            Notification::make()
                ->success()
                ->title("Successfully posted {$count} collections!")
                ->body('Total of ₱'.number_format($totalAmount, 2).' recorded and credited to borrower ledgers.')
                ->persistent()
                ->send();

            $this->start_receipt_no = '';
            $this->loadRows();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Error posting collections')
                ->body($e->getMessage())
                ->send();
        }
    }

    /** Filtered rows for the view (applies quick search and status filter). */
    public function getFilteredRowsProperty(): Collection
    {
        return collect($this->rows)->filter(function ($row) {
            // Status filter
            if ($this->status_filter === 'pending') {
                if ($row['is_already_paid'] || (float) ($row['amount'] ?? 0) > 0) {
                    return false;
                }
            } elseif ($this->status_filter === 'collected') {
                if (! $row['is_already_paid'] && (float) ($row['amount'] ?? 0) <= 0) {
                    return false;
                }
            }

            // Text search filter
            if ($term = trim($this->search)) {
                $term = strtolower($term);

                return str_contains(strtolower($row['borrower_name']), $term)
                    || str_contains(strtolower($row['loan_no']), $term)
                    || str_contains(strtolower($row['borrower_ref']), $term)
                    || str_contains(strtolower($row['borrower_address']), $term);
            }

            return true;
        });
    }

    public function getAreasProperty(): array
    {
        return Area::orderBy('name')->pluck('name', 'id')->all();
    }
}
