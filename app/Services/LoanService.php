<?php

namespace App\Services;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\LoanPlan;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private LoanCalculator $calculator) {}

    /**
     * Create a loan together with its repayment schedule and release voucher.
     * Release charges are deducted from the loan amount; the borrower receives the net proceeds.
     */
    public function create(array $data): Loan
    {
        $data = $this->applyPlan($data);

        $charges = static::computeCharges($data['charge_rules'] ?? [], $data['principal']);
        $totalCharges = round(array_sum(array_column($charges, 'amount')), 2);
        $netProceeds = round((float) $data['principal'] - $totalCharges, 2);

        if ($netProceeds <= 0) {
            throw ValidationException::withMessages([
                'principal' => 'Release charges of ₱'.number_format($totalCharges, 2).' must be less than the loan amount.',
            ]);
        }

        return DB::transaction(function () use ($data, $charges, $totalCharges, $netProceeds) {
            $borrower = Borrower::findOrFail($data['borrower_id']);

            $schedule = $this->calculator->schedule(
                principal: $data['principal'],
                interestRate: $data['interest_rate'],
                rateBasis: RateBasis::from($this->enumValue($data['rate_basis'])),
                method: InterestMethod::from($this->enumValue($data['interest_method'])),
                frequency: PaymentFrequency::from($this->enumValue($data['payment_frequency'])),
                term: (int) $data['term'],
                termUnit: TermUnit::from($this->enumValue($data['term_unit'] ?? TermUnit::Days)),
                startDate: Carbon::parse($data['start_date']),
            );

            $loan = Loan::create([
                ...$data,
                'area_id' => $borrower->area_id,
                'installment_count' => $schedule['installment_count'],
                'maturity_date' => $schedule['maturity_date'],
                'total_interest' => $schedule['total_interest'],
                'total_payable' => $schedule['total_payable'],
                'charges' => $charges,
                'total_charges' => $totalCharges,
                'net_proceeds' => $netProceeds,
                'voucher_no' => $this->nextVoucherNo(),
                'release_method' => $this->enumValue($data['release_method'] ?? 'cash') ?: 'cash',
                'released_by' => Auth::id(),
                'total_paid' => 0,
                'balance' => $schedule['total_payable'],
                'status' => LoanStatus::Active,
            ]);

            $loan->installments()->createMany($schedule['installments']);

            return $loan;
        });
    }

    /**
     * The owner sets interest and terms through loan plans. A plan's terms always win over
     * submitted values; only an administrator may create a loan with custom terms.
     */
    private function applyPlan(array $data): array
    {
        if (blank($data['loan_plan_id'] ?? null)) {
            if (! Auth::user()?->isAdmin() && Auth::check()) {
                throw ValidationException::withMessages(['loan_plan_id' => 'Please choose a loan plan.']);
            }

            // Custom terms: the owner may enter release charges on the form.
            return [...$data, 'loan_plan_id' => null, 'charge_rules' => $data['charge_rules'] ?? []];
        }

        $plan = LoanPlan::active()->find($data['loan_plan_id']);

        if (! $plan) {
            throw ValidationException::withMessages(['loan_plan_id' => 'This loan plan is no longer available.']);
        }

        $amount = (float) $data['principal'];

        if ($plan->min_amount !== null && $amount < (float) $plan->min_amount) {
            throw ValidationException::withMessages(['principal' => 'The minimum for this plan is ₱'.number_format((float) $plan->min_amount, 2).'.']);
        }

        if ($plan->max_amount !== null && $amount > (float) $plan->max_amount) {
            throw ValidationException::withMessages(['principal' => 'The maximum for this plan is ₱'.number_format((float) $plan->max_amount, 2).'.']);
        }

        return [
            ...$data,
            ...$plan->termValues(),
            'charge_rules' => $plan->charges ?? [],
            'required_savings' => $plan->required_savings,
        ];
    }

    /**
     * Turn charge rules into peso amounts for a loan amount.
     *
     * @param  list<array{name?: string, type?: string, value?: mixed}>  $rules  type "percent" (of the loan amount) or "fixed"
     * @return list<array{name: string, amount: float}>
     */
    public static function computeCharges(array $rules, float|string $principal): array
    {
        $charges = [];

        foreach ($rules as $rule) {
            $name = trim((string) ($rule['name'] ?? ''));
            $value = (float) ($rule['value'] ?? 0);

            if ($name === '' || $value <= 0) {
                continue;
            }

            $amount = ($rule['type'] ?? 'fixed') === 'percent'
                ? round((float) $principal * $value / 100, 2)
                : round($value, 2);

            $charges[] = ['name' => $name, 'amount' => $amount];
        }

        return $charges;
    }

    /** Six-digit voucher numbers continuing the Client's series (see lending.voucher.start). */
    private function nextVoucherNo(): string
    {
        // Numbers are zero-padded to the same width, so the text maximum is the numeric maximum.
        $last = (int) Loan::withoutGlobalScopes()->max('voucher_no');
        $next = max($last, config('lending.voucher.start') - 1) + 1;

        return str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Record a payment and apply it to the oldest unpaid installments.
     *
     * Payments are entered in date order: a payment cannot be dated before the loan's latest
     * payment. Otherwise the "balance after" on receipts already handed out would change and
     * no longer match the ledger.
     */
    public function recordPayment(Loan $loan, array $data): Payment
    {
        return DB::transaction(function () use ($loan, $data) {
            $loan = Loan::lockForUpdate()->findOrFail($loan->id);
            $amount = round((float) $data['amount'], 2);
            $date = Carbon::parse($data['payment_date'])->toDateString();

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
            }

            if ($amount > (float) $loan->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Amount exceeds the remaining balance of ₱'.number_format((float) $loan->balance, 2).'.',
                ]);
            }

            if ($date < $loan->start_date->toDateString()) {
                throw ValidationException::withMessages(['payment_date' => 'Payment date cannot be before the loan release date.']);
            }

            $latest = $this->latestPayment($loan);
            if ($latest && $date < $latest->payment_date->toDateString()) {
                throw ValidationException::withMessages([
                    'payment_date' => 'A later payment is already recorded (receipt '.$latest->receipt_no.' on '.$latest->payment_date->format('M d, Y').'). Enter payments in date order so receipts match the ledger.',
                ]);
            }

            $receiptNo = app(ReceiptBook::class)->claim($loan->area_id, $data['receipt_no'] ?? null);

            $payment = $loan->payments()->create([
                'area_id' => $loan->area_id,
                'payment_date' => $date,
                'amount' => $amount,
                'balance_after' => 0, // set by reallocate()
                'receipt_no' => $receiptNo,
                'remarks' => $data['remarks'] ?? null,
                'received_by' => Auth::id(),
            ]);

            $this->reallocate($loan);

            return $payment->refresh();
        });
    }

    /** Correct a mistyped receipt number or remarks. Amounts and balances are not touched. */
    public function updatePaymentDetails(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment->update([
                'receipt_no' => app(ReceiptBook::class)->claim($payment->area_id, $data['receipt_no'] ?? null, exceptPayment: $payment),
                'remarks' => $data['remarks'] ?? null,
            ]);

            return $payment;
        });
    }

    /**
     * Delete a payment. Only the latest payment of a loan can be deleted, so the balances on
     * every earlier receipt stay correct.
     */
    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $loan = Loan::lockForUpdate()->findOrFail($payment->loan_id);

            if (! $this->latestPayment($loan)?->is($payment)) {
                throw ValidationException::withMessages([
                    'payment' => 'Only the latest payment can be deleted. Deleting an earlier one would change the balances on receipts already given.',
                ]);
            }

            $payment->delete();
            $this->reallocate($loan);
        });
    }

    public function latestPayment(Loan $loan): ?Payment
    {
        return $loan->payments()->orderByDesc('payment_date')->orderByDesc('id')->first();
    }

    /**
     * Rebuild installment allocations, loan totals, and each payment's balance_after
     * from the full payment history. Keeps everything consistent after any change.
     */
    public function reallocate(Loan $loan): void
    {
        $installments = $loan->installments()->get();
        $payments = $loan->payments()->orderBy('payment_date')->orderBy('id')->get();

        foreach ($installments as $installment) {
            $installment->amount_paid = 0;
            $installment->paid_at = null;
        }

        $balance = (int) round((float) $loan->total_payable * 100);
        $totalPaid = 0;

        foreach ($payments as $payment) {
            $remaining = (int) round((float) $payment->amount * 100);
            $totalPaid += $remaining;
            $balance -= $remaining;

            foreach ($installments as $installment) {
                if ($remaining <= 0) {
                    break;
                }

                $due = (int) round((float) $installment->amount_due * 100);
                $paid = (int) round((float) $installment->amount_paid * 100);
                $apply = min($due - $paid, $remaining);

                if ($apply <= 0) {
                    continue;
                }

                $installment->amount_paid = ($paid + $apply) / 100;
                $remaining -= $apply;

                if ($paid + $apply >= $due) {
                    $installment->paid_at = $payment->payment_date;
                }
            }

            $payment->balance_after = $balance / 100;
            $payment->saveQuietly();
        }

        $installments->each->save();
        $loan->setRelation('installments', $installments);

        $loan->total_paid = $totalPaid / 100;
        $loan->balance = $balance / 100;
        $loan->status = $this->statusFor($loan);
        $loan->save();
    }

    /** Update Active/Overdue/Paid status for every open loan. Returns how many changed. */
    public function refreshStatuses(): int
    {
        $changed = 0;

        Loan::withoutGlobalScopes()
            ->where('status', '!=', LoanStatus::Paid)
            ->with('installments')
            ->chunkById(200, function ($loans) use (&$changed) {
                foreach ($loans as $loan) {
                    $status = $this->statusFor($loan);

                    if ($status !== $loan->status) {
                        $loan->update(['status' => $status]);
                        $changed++;
                    }
                }
            });

        return $changed;
    }

    public function statusFor(Loan $loan): LoanStatus
    {
        if ((float) $loan->balance <= 0) {
            return LoanStatus::Paid;
        }

        $cutoff = today()->subDays(config('lending.overdue_grace_days'));

        $hasLateInstallment = $loan->installments
            ->contains(fn ($i) => ! $i->isPaid() && $i->due_date->lt($cutoff));

        return $hasLateInstallment ? LoanStatus::Overdue : LoanStatus::Active;
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? $value->value : (string) $value;
    }
}
