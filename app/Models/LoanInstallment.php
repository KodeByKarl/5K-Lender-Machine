<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id',
        'number',
        'due_date',
        'principal',
        'interest',
        'amount_due',
        'amount_paid',
        'remaining_balance',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'date',
            'principal' => 'decimal:2',
            'interest' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function isPaid(): bool
    {
        return (float) $this->amount_paid >= (float) $this->amount_due;
    }
}
