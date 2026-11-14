<?php

namespace App\Support;

/**
 * Money is stored as whole sen (RM1 = 100 sen) so there are no rounding
 * surprises. This class turns sen into the text people see.
 */
class Money
{
    /**
     * 800 → "RM8", 450 → "RM4.50", 123456 → "RM1,234.56", -650 → "−RM6.50".
     */
    public static function format(int $sen): string
    {
        $sign = $sen < 0 ? '−' : '';
        $sen = abs($sen);

        $decimals = $sen % 100 === 0 ? 0 : 2;

        return $sign.'RM'.number_format($sen / 100, $decimals, '.', ',');
    }
}
