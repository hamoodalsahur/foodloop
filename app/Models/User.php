<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role, no_show_strikes and blocked_until are deliberately NOT fillable, so a
// sign-up form can never set them. Change them with forceFill() in trusted code.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Defaults for a brand-new user (the database has the same defaults).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Customer->value,
        'no_show_strikes' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'no_show_strikes' => 'integer',
            'blocked_until' => 'datetime',
        ];
    }

    /** The shop this account runs (sellers only). */
    public function seller(): HasOne
    {
        return $this->hasOne(Seller::class);
    }

    /** Reservations this user made as a customer. */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'customer_id');
    }

    /** Reports this user sent in. */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Blocked from reserving right now (too many no-shows)? */
    public function isBlocked(): bool
    {
        return $this->blocked_until !== null && $this->blocked_until->isFuture();
    }
}
