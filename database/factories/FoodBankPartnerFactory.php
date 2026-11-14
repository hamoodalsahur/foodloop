<?php

namespace Database\Factories;

use App\Models\FoodBankPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoodBankPartner>
 */
class FoodBankPartnerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Food Bank',
            'contact' => fake()->safeEmail(),
        ];
    }
}
