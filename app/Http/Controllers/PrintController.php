<?php

namespace App\Http\Controllers;

use App\Enums\SavingsTransactionType;
use App\Models\Area;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Print-friendly pages opened from the panel. Records resolve through the
 * BelongsToArea scope, so staff can only see and print their own Area's records.
 */
class PrintController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function savingsStatement(Request $request, SavingsAccount $account): View
    {
        $from = $request->date('from');
        $to = $request->date('to') ?? today();

        $opening = 0.0;
        if ($from) {
            $opening = (float) ($account->transactions()
                ->where('transaction_date', '<', $from)
                ->orderByDesc('transaction_date')->orderByDesc('id')
                ->value('running_balance') ?? 0);
        }

        $transactions = $account->transactions()
            ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from))
            ->where('transaction_date', '<=', $to)
            ->orderBy('transaction_date')->orderBy('id')
            ->get();

        return view('print.savings-statement', [
            'account' => $account->load('borrower', 'area'),
            'transactions' => $transactions,
            'opening' => $opening,
            'from' => $from ?? $account->opened_at,
            'to' => Carbon::parse($to),
        ]);
    }

    public function paymentReceipt(Payment $payment): View
    {
        $payment->load(['loan.borrower', 'area', 'receiver']);
        $loan = $payment->loan;

        return view('print.receipt', [
            'title' => config('lending.receipts.title'),
            'number' => $payment->receipt_no ?? '—',
            'date' => $payment->payment_date,
            'areaName' => $payment->area->name,
            'partyLabel' => 'Received from',
            'party' => $loan->borrower->full_name,
            'partyRef' => $loan->borrower->reference_no,
            'purpose' => 'Loan payment · '.$loan->loan_no.' ('.$loan->installment_count.' × '.strtolower($loan->payment_frequency->getLabel()).')',
            'remarks' => $payment->remarks,
            'amount' => $payment->amount,
            'balanceLabel' => 'Loan balance',
            'before' => (float) $payment->balance_after + (float) $payment->amount,
            'after' => $payment->balance_after,
            'movementLabel' => 'This payment',
            'sign' => '−',
            'isOut' => false,
            'staffName' => $payment->receiver?->name ?? '—',
            'encodedAt' => $payment->created_at,
        ]);
    }

    public function savingsReceipt(SavingsTransaction $transaction): View
    {
        $transaction->load(['account.borrower', 'area', 'recorder']);
        $isDeposit = $transaction->type === SavingsTransactionType::Deposit;
        $account = $transaction->account;

        return view('print.receipt', [
            'title' => $isDeposit ? config('lending.receipts.title') : 'Withdrawal Slip',
            'number' => $transaction->reference_no ?? '—',
            'date' => $transaction->transaction_date,
            'areaName' => $transaction->area->name,
            'partyLabel' => $isDeposit ? 'Received from' : 'Paid to',
            'party' => $account->borrower->full_name,
            'partyRef' => $account->borrower->reference_no,
            'purpose' => ($isDeposit ? 'Savings deposit' : 'Savings withdrawal').' · '.$account->account_no,
            'remarks' => $transaction->remarks,
            'amount' => $transaction->amount,
            'balanceLabel' => 'Savings balance',
            'before' => (float) $transaction->running_balance + ($isDeposit ? -1 : 1) * (float) $transaction->amount,
            'after' => $transaction->running_balance,
            'movementLabel' => $isDeposit ? 'This deposit' : 'This withdrawal',
            'sign' => $isDeposit ? '+' : '−',
            'isOut' => ! $isDeposit,
            'staffName' => $transaction->recorder?->name ?? '—',
            'encodedAt' => $transaction->created_at,
        ]);
    }

    public function receiptRegister(Request $request): View
    {
        $areaId = $this->reports->resolveArea($request->query('area'));
        [$from, $to] = $this->period($request);

        return view('print.reports.receipt-register', [
            ...$this->common($areaId),
            'from' => $from,
            'to' => $to,
            'groups' => $this->reports->receiptRegister($areaId, $from, $to),
        ]);
    }

    public function loanVoucher(Loan $loan): View
    {
        return view('print.voucher', [
            'loan' => $loan->load(['borrower', 'area', 'plan', 'releaser', 'installments' => fn ($q) => $q->orderBy('number')->limit(1)]),
        ]);
    }

    public function loanStatement(Loan $loan): View
    {
        return view('print.loan-statement', [
            'loan' => $loan->load(['borrower', 'area', 'plan', 'installments', 'payments' => fn ($q) => $q->orderBy('payment_date')->orderBy('id')]),
        ]);
    }

    public function collectionSheet(Request $request): View
    {
        $areaId = $this->reports->resolveArea($request->query('area'));
        $date = $request->date('date') ?? today();

        return view('print.reports.collection-sheet', [
            ...$this->common($areaId),
            'date' => $date,
            'loans' => $this->reports->collectionSheet($areaId, $date),
        ]);
    }

    public function payments(Request $request): View
    {
        $areaId = $this->reports->resolveArea($request->query('area'));
        [$from, $to] = $this->period($request);

        return view('print.reports.payments', [
            ...$this->common($areaId),
            'from' => $from,
            'to' => $to,
            'payments' => $this->reports->payments($areaId, $from, $to),
        ]);
    }

    public function outstanding(Request $request): View
    {
        $areaId = $this->reports->resolveArea($request->query('area'));

        return view('print.reports.outstanding', [
            ...$this->common($areaId),
            'loans' => $this->reports->outstandingLoans($areaId),
        ]);
    }

    public function overdue(Request $request): View
    {
        $areaId = $this->reports->resolveArea($request->query('area'));

        return view('print.reports.overdue', [
            ...$this->common($areaId),
            'loans' => $this->reports->overdueLoans($areaId),
        ]);
    }

    public function savings(Request $request): View
    {
        $areaId = $this->reports->resolveArea($request->query('area'));
        [$from, $to] = $this->period($request);

        return view('print.reports.savings', [
            ...$this->common($areaId),
            'from' => $from,
            'to' => $to,
            'accounts' => $this->reports->savingsSummary($areaId, $from, $to),
        ]);
    }

    public function borrowerHistory(Borrower $borrower): View
    {
        return view('print.reports.borrower', [
            'borrower' => $borrower->load([
                'area',
                'savingsAccount',
                'loans' => fn ($q) => $q->orderBy('start_date')->with(['plan', 'payments' => fn ($q) => $q->orderBy('payment_date')->orderBy('id')]),
            ]),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function common(?int $areaId): array
    {
        return [
            'areaId' => $areaId,
            'areaName' => $this->reports->areaName($areaId),
            'areas' => auth()->user()->isAdmin() ? Area::orderBy('name')->pluck('name', 'id') : collect(),
        ];
    }
}
