<?php

namespace App\Models;

use App\Enums\SavingsTransactionType;
use App\Models\Concerns\BelongsToArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SavingsTransaction extends Model
{
    use BelongsToArea, LogsActivity;

    protected $fillable = [
        'area_id',
        'savings_account_id',
        'transaction_date',
        'type',
        'amount',
        'running_balance',
        'reference_no',
        'remarks',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'type' => SavingsTransactionType::class,
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // running_balance is derived and recalculated; log only what the user entered.
        return LogOptions::defaults()->logFillable()->logExcept(['running_balance'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
