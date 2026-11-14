<?php

namespace App\Support;

/**
 * How a customer's name appears to a seller: first name and the first
 * letter of the next one ("Aina Rahman" → "Aina R."). Enough to match the
 * person at the counter without showing their full name.
 */
class CustomerName
{
    public static function short(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        return $parts[0].' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.';
    }
}
