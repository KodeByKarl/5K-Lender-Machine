<?php

namespace App\Models;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LoanPlan extends Model
{
    use LogsActivity;

    /** Loan fields copied from the plan when a loan is created. */
    public const TERM_FIELDS = ['interest_rate', 'rate_basis', 'interest_method', 'payment_frequency', 'term', 'term_unit'];

    protected $fillable = [
        'name',
        'interest_rate',
        'rate_basis',
        'interest_method',
        'payment_frequency',
        'term',
        'term_unit',
        'charges',
        'required_savings',
        'min_amount',
        'max_amount',
        'is_active',
        'sort',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'interest_rate' => 'decimal:4',
            'rate_basis' => RateBasis::class,
            'interest_method' => InterestMethod::class,
            'payment_frequency' => PaymentFrequency::class,
            'term_unit' => TermUnit::class,
            'charges' => 'array',
            'required_savings' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort')->orderBy('name');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /** e.g. "20% per loan term · daily for 60 days". */
    public function summary(): string
    {
        $rate = rtrim(rtrim((string) $this->interest_rate, '0'), '.');

        return $rate.'% '.strtolower($this->rate_basis->getLabel())
            .' · '.strtolower($this->payment_frequency->getLabel())
            .' for '.$this->term.' '.strtolower($this->term_unit->getLabel());
    }

    /** @return array<string, mixed> */
    public function termValues(): array
    {
        return collect(self::TERM_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $this->{$field} instanceof \BackedEnum ? $this->{$field}->value : $this->{$field}])
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
