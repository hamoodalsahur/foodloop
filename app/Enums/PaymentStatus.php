<?php

namespace App\Enums;

/**
 * Payment state of a reservation. No real money moves.
 */
enum PaymentStatus: string
{
    case SimulatedPaid = 'simulated_paid';
    case Unpaid = 'unpaid';

    /** The text shown to people. */
    public function label(): string
    {
        return match ($this) {
            self::SimulatedPaid => 'Paid (simulated)',
            self::Unpaid => 'Pay at pickup',
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
