<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;

/**
 * Who may do what with a shop profile.
 */
class SellerPolicy
{
    /** The shop's own account and admins can see the full profile (SSM number, certificate). */
    public function view(User $user, Seller $seller): bool
    {
        return $seller->user_id === $user->id || $user->isAdmin();
    }

    /** A seller edits only their own shop profile. */
    public function update(User $user, Seller $seller): bool
    {
        return $user->isSeller() && $seller->user_id === $user->id;
    }

    /** Approve, reject or suspend a shop: admins only. */
    public function review(User $user, Seller $seller): bool
    {
        return $user->isAdmin();
    }
}
