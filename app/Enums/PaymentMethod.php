<?php

namespace App\Enums;

/**
 * How the customer chose to pay. Payments are simulated only.
 */
enum PaymentMethod: string
{
    case PayNow = 'pay_now';
    case PayAtPickup = 'pay_at_pickup';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::PayNow => 'Pay now (simulated)',
            self::PayAtPickup => 'Pay at pickup',
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
