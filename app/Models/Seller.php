<?php

namespace App\Models;

use App\Enums\HalalStatus;
use App\Enums\SellerStatus;
use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// status, approved_at and info_request are not fillable: only an admin action changes them.
#[Fillable(['business_name', 'ssm_no', 'phone', 'address', 'lat', 'lng', 'halal_claim', 'halal_cert_path'])]
class Seller extends Model
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SellerStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'halal_claim' => HalalStatus::class,
            'status' => SellerStatus::class,
            'approved_at' => 'datetime',
            'info_requested_at' => 'datetime',
        ];
    }

    /** The account that owns this shop. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** Customers who asked to hear when this shop posts ("Notify me next time"). */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_follows')->withTimestamps();
    }

    /** Approved by an admin, so its listings may go live. */
    public function isApproved(): bool
    {
        return $this->status === SellerStatus::Approved;
    }

    /**
     * The rule behind the "halal certified" label: the shop claims to be
     * certified, uploaded the certificate, and an admin approved the shop.
     */
    public function hasApprovedHalalCertificate(): bool
    {
        return $this->isApproved()
            && $this->halal_claim === HalalStatus::Certified
            && filled($this->halal_cert_path);
    }
}
