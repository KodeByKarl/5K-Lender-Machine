<?php

namespace App\Support;

use NumberFormatter;

/** "1,250.50" → "One Thousand Two Hundred Fifty Pesos and 50/100", as written on receipts. */
class AmountInWords
{
    public static function pesos(float|string $amount): string
    {
        $cents = (int) round((float) $amount * 100);
        $pesos = intdiv($cents, 100);
        $centavos = $cents % 100;

        $words = ucwords(str_replace('-', ' ', (new NumberFormatter('en', NumberFormatter::SPELLOUT))->format($pesos)));

        return $words.' '.($pesos === 1 ? 'Peso' : 'Pesos').($centavos ? ' and '.str_pad((string) $centavos, 2, '0', STR_PAD_LEFT).'/100' : ' Only');
    }
}
