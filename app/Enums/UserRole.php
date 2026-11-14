<?php

namespace App\Enums;

/**
 * Who an account is.
 */
enum UserRole: string
{
    case Customer = 'customer';
    case Seller = 'seller';
    case Admin = 'admin';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Seller => 'Seller',
            self::Admin => 'Admin',
        };
    }

    /**
     * All stored values, e.g. for validation rules.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
