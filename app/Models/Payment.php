<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use BelongsToArea, LogsActivity;

    protected $fillable = [
        'area_id',
        'loan_id',
        'receipt_no',
        'payment_date',
        'amount',
        'balance_after',
        'remarks',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // balance_after is derived and recalculated; log only what the user entered.
        return LogOptions::defaults()->logFillable()->logExcept(['balance_after'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
