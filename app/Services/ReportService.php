<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\SavingsTransactionType;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanInstallment;
use App\Models\Payment;
use App\Models\SavingsAccount;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Figures for the dashboard and printed reports.
 *
 * Every query goes through the BelongsToArea scope, so staff only ever see their own Area.
 * Administrators may pass an $areaId to narrow to one Area, or null for all Areas (consolidated).
 */
class ReportService
{
    /** Staff are always limited to their own Area; administrators choose. */
    public function resolveArea(int|string|null $requested): ?int
    {
        $user = Auth::user();

        if ($user && ! $user->isAdmin()) {
            return $user->area_id;
        }

        return blank($requested) ? null : (int) $requested;
    }

    public function areaName(?int $areaId): string
    {
        return $areaId ? (Area::find($areaId)?->name ?? 'Unknown area') : 'All areas';
    }

    /** @return array<string, float|int> */
    public function summary(?int $areaId): array
    {
        $loans = fn () => $this->inArea(Loan::query(), $areaId);
        $open = fn () => $loans()->where('status', '!=', LoanStatus::Paid);
        $payments = fn () => $this->inArea(Payment::query(), $areaId);

        return [
            'borrowers' => $this->inArea(Borrower::query(), $areaId)->count(),
            'active_loans' => $open()->count(),
            'outstanding' => (float) $open()->sum('balance'),
            'released' => (float) $loans()->sum('principal'),
            'collected' => (float) $payments()->sum('amount'),
            'collected_today' => (float) $payments()->whereDate('payment_date', today())->sum('amount'),
            'due_today' => $this->dueOn(today(), $areaId),
            'overdue_loans' => $loans()->where('status', LoanStatus::Overdue)->count(),
            'past_due' => $this->pastDue($areaId),
            'savings' => (float) $this->inArea(SavingsAccount::query(), $areaId)->sum('balance'),
        ];
    }

    /** Daily collections for the last $days days, oldest first: ['Y-m-d' => amount]. */
    public function collectionsByDay(?int $areaId, int $days = 14): array
    {
        $from = today()->subDays($days - 1);

        $totals = $this->inArea(Payment::query(), $areaId)
            ->whereDate('payment_date', '>=', $from)
            ->selectRaw('date(payment_date) as day, sum(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $series[$d->toDateString()] = (float) ($totals[$d->toDateString()] ?? 0);
        }

        return $series;
    }

    /** Unpaid amount of installments falling due on $date. */
    public function dueOn(CarbonInterface $date, ?int $areaId): float
    {
        return (float) $this->openInstallments($areaId)
            ->whereDate('due_date', $date)
            ->selectRaw('coalesce(sum(amount_due - amount_paid), 0) as total')
            ->value('total');
    }

    /** Unpaid amount of installments already past their due date. */
    public function pastDue(?int $areaId, ?CarbonInterface $asOf = null): float
    {
        return (float) $this->openInstallments($areaId)
            ->whereDate('due_date', '<', $asOf ?? today())
            ->selectRaw('coalesce(sum(amount_due - amount_paid), 0) as total')
            ->value('total');
    }

    /** @return Collection<int, Loan> */
    public function outstandingLoans(?int $areaId): Collection
    {
        return $this->inArea(Loan::query(), $areaId)
            ->where('status', '!=', LoanStatus::Paid)
            ->with(['borrower', 'area'])
            ->orderBy('area_id')->orderBy('maturity_date')
            ->get();
    }

    /**
     * Overdue loans with how late they are and how much is past due.
     *
     * @return Collection<int, Loan>
     */
    public function overdueLoans(?int $areaId, ?CarbonInterface $asOf = null): Collection
    {
        $asOf ??= today();

        return $this->inArea(Loan::query(), $areaId)
            ->where('status', LoanStatus::Overdue)
            ->with(['borrower', 'area', 'installments' => fn ($q) => $q->whereColumn('amount_paid', '<', 'amount_due')->whereDate('due_date', '<', $asOf)])
            ->get()
            ->each(function (Loan $loan) use ($asOf) {
                $oldest = $loan->installments->min('due_date');
                $loan->days_late = $oldest ? (int) $oldest->diffInDays($asOf) : 0;
                $loan->missed_installments = $loan->installments->count();
                $loan->past_due = $loan->installments->sum(fn ($i) => (float) $i->amount_due - (float) $i->amount_paid);
            })
            ->sortByDesc('days_late')
            ->values();
    }

    /** @return Collection<int, Payment> */
    public function payments(?int $areaId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->inArea(Payment::query(), $areaId)
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to)
            ->with(['loan.borrower', 'area', 'receiver'])
            ->orderBy('payment_date')->orderBy('id')
            ->get();
    }

    /**
     * Savings totals per borrower, with deposits and withdrawals within the period.
     *
     * @return Collection<int, SavingsAccount>
     */
    public function savingsSummary(?int $areaId, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $period = fn (SavingsTransactionType $type) => fn ($q) => $q->where('type', $type)
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to));

        return $this->inArea(SavingsAccount::query(), $areaId)
            ->with(['borrower', 'area'])
            ->withSum(['transactions as deposits' => $period(SavingsTransactionType::Deposit)], 'amount')
            ->withSum(['transactions as withdrawals' => $period(SavingsTransactionType::Withdrawal)], 'amount')
            ->orderBy('area_id')->orderBy('account_no')
            ->get();
    }

    /**
     * The collector's route for $date: every loan with an installment due that day (even if already
     * paid, so the sheet is the same whenever it is printed) or with arrears from earlier days.
     *
     * @return Collection<int, Loan>
     */
    public function collectionSheet(?int $areaId, CarbonInterface $date): Collection
    {
        return $this->inArea(Loan::query(), $areaId)
            ->whereDate('start_date', '<', $date)
            ->where(fn ($q) => $q
                ->where('status', '!=', LoanStatus::Paid)
                ->orWhereHas('payments', fn ($p) => $p->whereDate('payment_date', $date)))
            ->with([
                'borrower',
                'area',
                'installments' => fn ($q) => $q->whereDate('due_date', '<=', $date)
                    ->where(fn ($q) => $q->whereColumn('amount_paid', '<', 'amount_due')->orWhereDate('due_date', $date)),
                'payments' => fn ($q) => $q->whereDate('payment_date', $date),
            ])
            ->get()
            ->each(function (Loan $loan) use ($date) {
                $remaining = fn ($i) => max(0, (float) $i->amount_due - (float) $i->amount_paid);
                $today = $loan->installments->filter(fn ($i) => $i->due_date->isSameDay($date));

                $loan->due_today = $today->sum(fn ($i) => (float) $i->amount_due);
                $loan->arrears = $loan->installments->filter(fn ($i) => $i->due_date->lt($date))->sum($remaining);
                $loan->to_collect = $today->sum($remaining) + $loan->arrears;
                $loan->paid_today = $loan->payments->sum(fn ($p) => (float) $p->amount);
            })
            ->filter(fn (Loan $loan) => $loan->due_today > 0 || $loan->arrears > 0 || $loan->paid_today > 0)
            ->sortBy(fn (Loan $loan) => [$loan->area_id, $loan->borrower->last_name])
            ->values();
    }

    /**
     * Every booklet receipt (loan payments and savings deposits) in number order per Area,
     * with gaps in the numbering flagged, so paper receipts can be checked against the system.
     * A gap of more than 50 numbers is treated as a new booklet rather than missing receipts.
     *
     * @return Collection<int, array{area: string, rows: list<array<string, mixed>>, count: int, total: float, missing: int}>
     */
    public function receiptRegister(?int $areaId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $payments = $this->inArea(Payment::query(), $areaId)
            ->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)
            ->with(['loan.borrower', 'area', 'receiver'])
            ->get()
            ->map(fn (Payment $p) => [
                'number' => (string) $p->receipt_no,
                'date' => $p->payment_date,
                'kind' => 'Loan payment',
                'party' => $p->loan->borrower->full_name,
                'account' => $p->loan->loan_no,
                'amount' => (float) $p->amount,
                'by' => $p->receiver?->name,
                'area_id' => $p->area_id,
                'area' => $p->area->name,
                'url' => route('print.receipts.payment', $p),
            ]);

        $deposits = $this->inArea(\App\Models\SavingsTransaction::query(), $areaId)
            ->where('type', SavingsTransactionType::Deposit)
            ->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)
            ->with(['account.borrower', 'area', 'recorder'])
            ->get()
            ->map(fn ($t) => [
                'number' => (string) $t->reference_no,
                'date' => $t->transaction_date,
                'kind' => 'Savings deposit',
                'party' => $t->account->borrower->full_name,
                'account' => $t->account->account_no,
                'amount' => (float) $t->amount,
                'by' => $t->recorder?->name,
                'area_id' => $t->area_id,
                'area' => $t->area->name,
                'url' => route('print.receipts.savings', $t),
            ]);

        return $payments->concat($deposits)
            ->groupBy('area_id')
            ->sortKeys()
            ->map(function (Collection $receipts) {
                $sorted = $receipts->sortBy(fn ($r) => $this->receiptSortKey($r['number']), SORT_NATURAL)->values();
                $rows = [];
                $missing = 0;
                $previous = null;

                foreach ($sorted as $receipt) {
                    [$prefix, $digits] = $this->splitReceipt($receipt['number']);

                    if ($previous && $previous['prefix'] === $prefix && $digits !== null && $previous['digits'] !== null) {
                        $skipped = $digits - $previous['digits'] - 1;

                        if ($skipped === -1) {
                            $rows[] = ['type' => 'duplicate', 'number' => $receipt['number']];
                        } elseif ($skipped > 0 && $skipped <= 50) {
                            $width = strlen((string) $previous['raw_digits']);
                            $pad = fn ($n) => $prefix.str_pad((string) $n, $width, '0', STR_PAD_LEFT);
                            $rows[] = ['type' => 'gap', 'from' => $pad($previous['digits'] + 1), 'to' => $pad($digits - 1), 'count' => $skipped];
                            $missing += $skipped;
                        } elseif ($skipped > 50) {
                            $rows[] = ['type' => 'jump', 'from' => $previous['number'], 'to' => $receipt['number']];
                        }
                    }

                    $rows[] = ['type' => 'receipt', ...$receipt];
                    $previous = ['prefix' => $prefix, 'digits' => $digits, 'raw_digits' => $this->splitReceipt($receipt['number'], raw: true)[1], 'number' => $receipt['number']];
                }

                return [
                    'area' => $receipts->first()['area'],
                    'rows' => $rows,
                    'count' => $receipts->count(),
                    'total' => $receipts->sum('amount'),
                    'missing' => $missing,
                ];
            })
            ->values();
    }

    /** "OR-0123" → ["OR-", 123]; numbers without trailing digits → [number, null]. */
    private function splitReceipt(string $number, bool $raw = false): array
    {
        if (preg_match('/^(.*?)(\d+)$/', $number, $m)) {
            return [$m[1], $raw ? $m[2] : (int) $m[2]];
        }

        return [$number, null];
    }

    private function receiptSortKey(string $number): string
    {
        [$prefix, $digits] = $this->splitReceipt($number);

        return $prefix.str_pad((string) ($digits ?? ''), 12, '0', STR_PAD_LEFT);
    }

    private function openInstallments(?int $areaId): Builder
    {
        return LoanInstallment::query()
            ->whereColumn('amount_paid', '<', 'amount_due')
            ->whereHas('loan', fn ($q) => $this->inArea($q, $areaId)->where('status', '!=', LoanStatus::Paid));
    }

    private function inArea(Builder $query, ?int $areaId): Builder
    {
        return $query->when($areaId, fn ($q) => $q->where($q->qualifyColumn('area_id'), $areaId));
    }
}
