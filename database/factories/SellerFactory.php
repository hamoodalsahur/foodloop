<?php

namespace Database\Factories;

use App\Enums\HalalStatus;
use App\Enums\SellerStatus;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    /**
     * A shop that has applied and is waiting for an admin.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->seller(),
            'business_name' => fake()->company(),
            'ssm_no' => fake()->numerify('20##0#######'),
            'phone' => fake()->numerify('01#-### ####'),
            'address' => fake()->streetAddress(),
            // Within about 1 km of "you are here" (the campus).
            'lat' => config('foodloop.demo_origin.lat') + fake()->randomFloat(5, -0.009, 0.009),
            'lng' => config('foodloop.demo_origin.lng') + fake()->randomFloat(5, -0.009, 0.009),
            'halal_claim' => HalalStatus::MuslimOwned,
            'halal_cert_path' => null,
            'status' => SellerStatus::Pending,
            'approved_at' => null,
        ];
    }

    /** Waiting for an admin (this is also the default). */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SellerStatus::Pending, 'approved_at' => null]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SellerStatus::Rejected]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => SellerStatus::Suspended]);
    }

    /** Claims halal certification and has uploaded the certificate. */
    public function certified(): static
    {
        return $this->state(fn (array $attributes) => [
            'halal_claim' => HalalStatus::Certified,
            'halal_cert_path' => 'halal-certs/test-certificate.pdf',
        ]);
    }

    public function notHalal(): static
    {
        return $this->state(fn (array $attributes) => ['halal_claim' => HalalStatus::NotHalal]);
    }
}
