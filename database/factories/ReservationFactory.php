<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Plain reservation rows for tests and demo data. Real reservations are
 * made by App\Services\ReservationService (Stage 2), which also moves stock.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * One portion, pay at pickup, still waiting to be collected.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'customer_id' => User::factory(),
            'quantity' => 1,
            'total_sen' => fn (array $attributes) => Listing::findOrFail($attributes['listing_id'])->price_sen
                * $attributes['quantity'],
            'payment_method' => PaymentMethod::PayAtPickup,
            'payment_status' => PaymentStatus::Unpaid,
            'pickup_code' => fake()->numerify('####'),
            'status' => ReservationStatus::Reserved,
            'expires_at' => fn (array $attributes) => Listing::findOrFail($attributes['listing_id'])->pickup_end
                ->addMinutes(config('foodloop.grace_minutes')),
            'collected_at' => null,
            'cancelled_at' => null,
            'late_cancel' => false,
        ];
    }

    /** Paid up front (simulated, no real money). */
    public function paidNow(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => PaymentMethod::PayNow,
            'payment_status' => PaymentStatus::SimulatedPaid,
        ]);
    }

    /** Picked up. Pay-at-pickup reservations are paid at that moment. */
    public function collected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Collected,
            'payment_status' => PaymentStatus::SimulatedPaid,
            'collected_at' => now(),
        ]);
    }

    public function cancelled(bool $late = false): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
            'cancelled_at' => now(),
            'late_cancel' => $late,
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ReservationStatus::NoShow]);
    }
}
