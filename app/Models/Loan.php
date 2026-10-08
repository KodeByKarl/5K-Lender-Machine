<?php

namespace App\Models;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use App\Models\Concerns\BelongsToArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Loan extends Model
{
    use BelongsToArea, LogsActivity;

    protected $fillable = [
        'area_id',
        'borrower_id',
        'loan_plan_id',
        'loan_no',
        'voucher_no',
        'release_method',
        'release_reference',
        'principal',
        'interest_rate',
        'rate_basis',
        'interest_method',
        'payment_frequency',
        'term',
        'term_unit',
        'installment_count',
        'start_date',
        'maturity_date',
        'total_interest',
        'total_payable',
        'charges',
        'total_charges',
        'net_proceeds',
        'required_savings',
        'total_paid',
        'balance',
        'status',
        'remarks',
        'released_by',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'interest_rate' => 'decimal:4',
            'rate_basis' => RateBasis::class,
            'interest_method' => InterestMethod::class,
            'payment_frequency' => PaymentFrequency::class,
            'term_unit' => TermUnit::class,
            'start_date' => 'date',
            'maturity_date' => 'date',
            'total_interest' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'charges' => 'array',
            'total_charges' => 'decimal:2',
            'net_proceeds' => 'decimal:2',
            'required_savings' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'status' => LoanStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Loan $loan) {
            $next = (static::withoutGlobalScopes()->max('id') ?? 0) + 1;
            $loan->loan_no ??= 'LN-'.now()->format('Y').'-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        });
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LoanPlan::class, 'loan_plan_id');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
