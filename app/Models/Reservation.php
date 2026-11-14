<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Support\Money;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Status changes go through App\Services\ReservationService only (Stage 2).
#[Fillable([
    'quantity', 'total_sen', 'payment_method', 'payment_status', 'pickup_code',
    'status', 'expires_at', 'collected_at', 'cancelled_at', 'late_cancel',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ReservationStatus::Reserved->value,
        'late_cancel' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'total_sen' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'status' => ReservationStatus::class,
            'expires_at' => 'datetime',
            'collected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'late_cancel' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** The user who reserved. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** "RM16" */
    protected function totalFormatted(): Attribute
    {
        return Attribute::get(fn () => Money::format($this->total_sen));
    }
}
