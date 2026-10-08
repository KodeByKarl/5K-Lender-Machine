<?php

namespace App\Services;

use App\Enums\SavingsTransactionType;
use App\Models\Payment;
use App\Models\SavingsTransaction;
use Illuminate\Validation\ValidationException;

/**
 * Receipt numbers come from the Area's pre-printed booklet and are typed in by staff.
 * One booklet is used for loan payments and savings deposits, so a number may be used
 * only once per Area across both. This is what keeps paper receipts and ledgers matched.
 */
class ReceiptBook
{
    public function normalize(?string $number): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $number));
    }

    /**
     * @throws ValidationException when the number is blank or already used in this Area.
     */
    public function claim(int $areaId, ?string $number, string $field = 'receipt_no', ?Payment $exceptPayment = null, ?SavingsTransaction $exceptTransaction = null): string
    {
        $number = $this->normalize($number);

        if ($number === '') {
            throw ValidationException::withMessages([$field => 'Enter the receipt number from the receipt booklet.']);
        }

        if ($used = $this->findUse($areaId, $number, $exceptPayment, $exceptTransaction)) {
            throw ValidationException::withMessages([$field => "Receipt no. {$number} is already used for {$used}."]);
        }

        return $number;
    }

    /** Describes where a receipt number is already used in the Area, or null if it is free. */
    private function findUse(int $areaId, string $number, ?Payment $exceptPayment, ?SavingsTransaction $exceptTransaction): ?string
    {
        $payment = Payment::withoutGlobalScopes()
            ->with(['loan' => fn ($q) => $q->withoutGlobalScopes()->with(['borrower' => fn ($q) => $q->withoutGlobalScopes()])])
            ->where('area_id', $areaId)
            ->where('receipt_no', $number)
            ->when($exceptPayment, fn ($q) => $q->whereKeyNot($exceptPayment->getKey()))
            ->first();

        if ($payment) {
            return 'a loan payment on '.$payment->payment_date->format('M d, Y').' ('.$payment->loan->borrower->full_name.')';
        }

        $deposit = SavingsTransaction::withoutGlobalScopes()
            ->with(['account' => fn ($q) => $q->withoutGlobalScopes()->with(['borrower' => fn ($q) => $q->withoutGlobalScopes()])])
            ->where('area_id', $areaId)
            ->where('type', SavingsTransactionType::Deposit)
            ->where('reference_no', $number)
            ->when($exceptTransaction, fn ($q) => $q->whereKeyNot($exceptTransaction->getKey()))
            ->first();

        if ($deposit) {
            return 'a savings deposit on '.$deposit->transaction_date->format('M d, Y').' ('.$deposit->account->borrower->full_name.')';
        }

        return null;
    }
}
