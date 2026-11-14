<?php

namespace App\Models;

use Database\Factories\FoodBankPartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact'])]
class FoodBankPartner extends Model
{
    /** @use HasFactory<FoodBankPartnerFactory> */
    use HasFactory;

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
