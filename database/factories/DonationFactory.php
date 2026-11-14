<?php

namespace Database\Factories;

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory()->closed(),
            'food_bank_partner_id' => FoodBankPartner::factory(),
            'quantity' => 2,
            'status' => DonationStatus::Offered,
        ];
    }
}
