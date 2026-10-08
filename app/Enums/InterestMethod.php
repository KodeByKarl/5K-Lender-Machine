<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InterestMethod: string implements HasLabel
{
    case Flat = 'flat';
    case Diminishing = 'diminishing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Flat => 'Flat / Add-on',
            self::Diminishing => 'Diminishing Balance',
        };
    }
}
