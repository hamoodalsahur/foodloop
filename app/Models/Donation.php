<?php

namespace App\Models;

use App\Enums\DonationStatus;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quantity', 'status'])]
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => DonationStatus::Offered->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => DonationStatus::class,
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(FoodBankPartner::class, 'food_bank_partner_id');
    }
}
