<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TermUnit: string implements HasLabel
{
    case Days = 'days';
    case Months = 'months';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    /** Length of a term in months, used to apply per-month and per-year rates. A month counts as 30 days. */
    public function toMonths(int $term): float
    {
        return match ($this) {
            self::Days => $term / 30,
            self::Months => $term,
        };
    }
}
