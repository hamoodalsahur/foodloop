<?php

namespace Database\Factories;

use App\Enums\HalalStatus;
use App\Enums\ListingCategory;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * A live listing from an approved shop: 5 portions at RM8 instead of
     * RM20, pickup from one hour to three hours from now.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory()->approved(),
            'type' => ListingType::Regular,
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => ListingCategory::Meals,
            'photo_path' => null,
            'original_price_sen' => 2000,
            'price_sen' => 800,
            'quantity_total' => 5,
            'quantity_available' => 5,
            'pickup_start' => now()->addHour()->startOfMinute(),
            'pickup_end' => now()->addHours(3)->startOfMinute(),
            'halal_status' => HalalStatus::MuslimOwned,
            'allergens' => [],
            'offer_leftovers' => false,
            'status' => ListingStatus::Live,
            'published_at' => now(),
            'closed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ListingStatus::Paused]);
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::SoldOut,
            'quantity_available' => 0,
        ]);
    }

    /** The pickup window is over and the listing is closed. */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ListingStatus::Closed,
            'pickup_start' => now()->subHours(3)->startOfMinute(),
            'pickup_end' => now()->subHour()->startOfMinute(),
            'closed_at' => now()->subHour(),
        ]);
    }

    public function bundle(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ListingType::Bundle]);
    }

    public function flash(): static
    {
        return $this->state(fn (array $attributes) => ['type' => ListingType::Flash]);
    }

    /** Halal certified, from a shop with an approved certificate. */
    public function certified(): static
    {
        return $this->state(fn (array $attributes) => [
            'seller_id' => Seller::factory()->approved()->certified(),
            'halal_status' => HalalStatus::Certified,
        ]);
    }

    /** Set the pickup window in minutes from now (negative = in the past). */
    public function window(int $startInMinutes, int $endInMinutes): static
    {
        return $this->state(fn (array $attributes) => [
            'pickup_start' => now()->addMinutes($startInMinutes)->startOfMinute(),
            'pickup_end' => now()->addMinutes($endInMinutes)->startOfMinute(),
        ]);
    }
}
