<?php

namespace App\Models;

use App\Enums\HalalStatus;
use App\Enums\ListingCategory;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Support\Money;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'type', 'title', 'description', 'category', 'photo_path',
    'original_price_sen', 'price_sen', 'quantity_total', 'quantity_available',
    'pickup_start', 'pickup_end', 'halal_status', 'allergens', 'offer_leftovers',
    'status', 'published_at', 'closed_at',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    /**
     * Km from "you are here" to the shop. Not a database column: filled in by
     * App\Queries\DealsQuery (or by hand) when a page shows distances.
     */
    public ?float $distanceKm = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => ListingType::Regular->value,
        'status' => ListingStatus::Draft->value,
        'offer_leftovers' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ListingType::class,
            'category' => ListingCategory::class,
            'halal_status' => HalalStatus::class,
            'status' => ListingStatus::class,
            'original_price_sen' => 'integer',
            'price_sen' => 'integer',
            'quantity_total' => 'integer',
            'quantity_available' => 'integer',
            'pickup_start' => 'datetime',
            'pickup_end' => 'datetime',
            'allergens' => 'array',
            'offer_leftovers' => 'boolean',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Business rules from docs/BUILD_PLAN.md §3, checked on every save so no
     * page or script can skip them.
     */
    protected static function booted(): void
    {
        static::saving(function (Listing $listing) {
            // "certified" only with an approved halal certificate on file.
            if ($listing->halal_status === HalalStatus::Certified
                && ($listing->isDirty('halal_status') || ! $listing->exists)
                && ! $listing->seller?->hasApprovedHalalCertificate()) {
                throw BusinessRuleViolation::certifiedNeedsCertificate();
            }

            // Only an approved seller can put a listing live.
            if ($listing->status === ListingStatus::Live
                && ($listing->isDirty('status') || ! $listing->exists)
                && ! $listing->seller?->isApproved()) {
                throw BusinessRuleViolation::sellerNotApproved();
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** Listings customers can see right now. */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ListingStatus::Live);
    }

    /**
     * Deals a customer can reserve right now: live, pickup window not over,
     * and from a shop that is (still) approved.
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ListingStatus::Live)
            ->where('pickup_end', '>', now())
            ->whereHas('seller', fn (Builder $seller) => $seller->where('status', SellerStatus::Approved));
    }

    /** Can be reserved right now (the same test as the `open` scope, for one listing). */
    public function isOpen(): bool
    {
        return $this->status === ListingStatus::Live
            && $this->pickup_end->isFuture()
            && $this->seller->isApproved();
    }

    /** Was on the deals page at some point: live, sold out or closed (not a draft, not paused). */
    public function isPublished(): bool
    {
        return in_array($this->status, [ListingStatus::Live, ListingStatus::SoldOut, ListingStatus::Closed], true);
    }

    /** Reservations are taken until the pickup window ends. */
    public function reservationsCloseAt(): Carbon
    {
        return $this->pickup_end;
    }

    /** The last moment a reservation can be cancelled for free. */
    public function freeCancelUntil(): Carbon
    {
        return $this->pickup_start->copy()->subMinutes(config('foodloop.free_cancel_minutes'));
    }

    /**
     * Portions nobody collected: the unsold ones plus the no-shows (those are
     * never put back on sale). Final once no reservation is still `reserved`.
     */
    public function leftovers(): int
    {
        return $this->quantity_available
            + (int) $this->reservations()->where('status', ReservationStatus::NoShow)->sum('quantity');
    }

    /** "RM8" */
    protected function priceFormatted(): Attribute
    {
        return Attribute::get(fn () => Money::format($this->price_sen));
    }

    /** "RM20" */
    protected function originalPriceFormatted(): Attribute
    {
        return Attribute::get(fn () => Money::format($this->original_price_sen));
    }

    /** What the customer saves on one portion, in sen. */
    protected function savingSen(): Attribute
    {
        return Attribute::get(fn () => $this->original_price_sen - $this->price_sen);
    }

    /** 60 for "−60%". Whole number, 0 when there is no original price. */
    protected function discountPercent(): Attribute
    {
        return Attribute::get(fn () => $this->original_price_sen > 0
            ? (int) round($this->saving_sen / $this->original_price_sen * 100)
            : 0);
    }
}
