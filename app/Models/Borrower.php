<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;

class Borrower extends Model
{
    use BelongsToArea, LogsActivity;

    protected $fillable = [
        'area_id',
        'reference_no',
        'first_name',
        'middle_name',
        'last_name',
        'contact_no',
        'address',
        'birth_date',
        'occupation',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Borrower $borrower) {
            $borrower->reference_no ??= static::nextReferenceNo();
        });
    }

    public static function nextReferenceNo(): string
    {
        $next = (static::withoutGlobalScopes()->max('id') ?? 0) + 1;

        return 'BRW-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim($this->last_name.', '.$this->first_name.' '.$this->middle_name));
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function savingsAccount(): HasOne
    {
        return $this->hasOne(SavingsAccount::class);
    }
}
