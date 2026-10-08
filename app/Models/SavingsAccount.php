<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class SavingsAccount extends Model
{
    use BelongsToArea, LogsActivity;

    protected $fillable = [
        'area_id',
        'borrower_id',
        'account_no',
        'opened_at',
        'balance',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SavingsAccount $account) {
            $next = (static::withoutGlobalScopes()->max('id') ?? 0) + 1;
            $account->account_no ??= 'SAV-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        });
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }
}
