<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RateBasis: string implements HasLabel
{
    case PerMonth = 'per_month';
    case PerTerm = 'per_term';
    case PerYear = 'per_year';

    public function getLabel(): string
    {
        return match ($this) {
            self::PerMonth => 'Per Month',
            self::PerTerm => 'Per Loan Term',
            self::PerYear => 'Per Year',
        };
    }
}
