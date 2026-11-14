<?php

namespace Database\Factories;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'listing_id' => Listing::factory(),
            'reason' => ReportReason::FoodQuality,
            'details' => fake()->sentence(),
            'status' => ReportStatus::Open,
            'admin_note' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ];
    }

    /** Closed by an admin. */
    public function actioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::Actioned,
            'resolved_by' => User::factory()->admin(),
            'resolved_at' => now(),
        ]);
    }
}
