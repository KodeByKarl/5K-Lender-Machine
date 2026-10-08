<?php

namespace App\Services;

use App\Enums\SavingsTransactionType;
use App\Models\Borrower;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavingsService
{
    public function open(Borrower $borrower, ?string $openedAt = null): SavingsAccount
    {
        if ($borrower->savingsAccount()->exists()) {
            throw ValidationException::withMessages(['borrower_id' => 'This borrower already has a savings account.']);
        }

        return SavingsAccount::create([
            'area_id' => $borrower->area_id,
            'borrower_id' => $borrower->id,
            'opened_at' => $openedAt ?? today(),
            'balance' => 0,
        ]);
    }

    public function deposit(SavingsAccount $account, array $data): SavingsTransaction
    {
        return $this->record($account, SavingsTransactionType::Deposit, $data);
    }

    public function withdraw(SavingsAccount $account, array $data): SavingsTransaction
    {
        return $this->record($account, SavingsTransactionType::Withdrawal, $data);
    }

    /**
     * Delete a transaction. Only the latest one can be deleted, so the running balance on
     * every earlier receipt stays correct.
     */
    public function delete(SavingsTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $account = SavingsAccount::lockForUpdate()->findOrFail($transaction->savings_account_id);

            if (! $this->latest($account)?->is($transaction)) {
                throw ValidationException::withMessages([
                    'transaction' => 'Only the latest transaction can be deleted. Deleting an earlier one would change the balances on receipts already given.',
                ]);
            }

            $transaction->delete();
            $this->recalculate($account, 'This transaction cannot be deleted.');
        });
    }

    /** Correct a mistyped receipt / reference number or remarks. Amounts and balances are not touched. */
    public function updateDetails(SavingsTransaction $transaction, array $data): SavingsTransaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            $reference = $transaction->type === SavingsTransactionType::Deposit
                ? app(ReceiptBook::class)->claim($transaction->area_id, $data['reference_no'] ?? null, 'reference_no', exceptTransaction: $transaction)
                : (app(ReceiptBook::class)->normalize($data['reference_no'] ?? null) ?: null);

            $transaction->update(['reference_no' => $reference, 'remarks' => $data['remarks'] ?? null]);

            return $transaction;
        });
    }

    public function latest(SavingsAccount $account): ?SavingsTransaction
    {
        return $account->transactions()->orderByDesc('transaction_date')->orderByDesc('id')->first();
    }

    /**
     * Transactions are entered in date order (never before the latest one), so the running
     * balance printed on a receipt never changes afterwards. Deposits need a receipt number
     * from the Area's booklet; withdrawals may carry a voucher number.
     */
    private function record(SavingsAccount $account, SavingsTransactionType $type, array $data): SavingsTransaction
    {
        return DB::transaction(function () use ($account, $type, $data) {
            $account = SavingsAccount::lockForUpdate()->findOrFail($account->id);
            $amount = round((float) $data['amount'], 2);
            $date = Carbon::parse($data['transaction_date'])->toDateString();

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            if ($type === SavingsTransactionType::Withdrawal && $amount > (float) $account->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Withdrawal exceeds the available balance of ₱'.number_format((float) $account->balance, 2).'.',
                ]);
            }

            if ($date < $account->opened_at->toDateString()) {
                throw ValidationException::withMessages(['transaction_date' => 'Date cannot be before the account was opened.']);
            }

            $latest = $this->latest($account);
            if ($latest && $date < $latest->transaction_date->toDateString()) {
                throw ValidationException::withMessages([
                    'transaction_date' => 'A later transaction is already recorded ('.$latest->transaction_date->format('M d, Y').'). Enter transactions in date order so receipts match the ledger.',
                ]);
            }

            $reference = $type === SavingsTransactionType::Deposit
                ? app(ReceiptBook::class)->claim($account->area_id, $data['reference_no'] ?? null, 'reference_no')
                : (app(ReceiptBook::class)->normalize($data['reference_no'] ?? null) ?: null);

            $transaction = $account->transactions()->create([
                'area_id' => $account->area_id,
                'transaction_date' => $date,
                'type' => $type,
                'amount' => $amount,
                'running_balance' => 0, // set by recalculate()
                'reference_no' => $reference,
                'remarks' => $data['remarks'] ?? null,
                'recorded_by' => Auth::id(),
            ]);

            $this->recalculate($account, 'This withdrawal would make the balance negative.');

            return $transaction->refresh();
        });
    }

    /** Rebuild running balances in date order and the account balance. Rejects any negative point. */
    private function recalculate(SavingsAccount $account, string $negativeMessage): void
    {
        $balance = 0;

        $transactions = $account->transactions()->orderBy('transaction_date')->orderBy('id')->get();

        foreach ($transactions as $transaction) {
            $cents = (int) round((float) $transaction->amount * 100);
            $balance += $transaction->type === SavingsTransactionType::Deposit ? $cents : -$cents;

            if ($balance < 0) {
                throw ValidationException::withMessages(['amount' => $negativeMessage]);
            }

            if ((int) round((float) $transaction->running_balance * 100) !== $balance) {
                $transaction->running_balance = $balance / 100;
                $transaction->saveQuietly();
            }
        }

        $account->update(['balance' => $balance / 100]);
    }
}
