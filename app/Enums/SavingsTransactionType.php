<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SavingsTransactionType: string implements HasColor, HasLabel
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Deposit => 'success',
            self::Withdrawal => 'warning',
        };
    }
}
