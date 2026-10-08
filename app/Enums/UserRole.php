<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Staff = 'staff';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Area Staff',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'primary',
            self::Staff => 'gray',
        };
    }
}
