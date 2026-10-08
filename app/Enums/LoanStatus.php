<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Overdue = 'overdue';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Overdue => 'danger',
            self::Paid => 'success',
        };
    }
}
