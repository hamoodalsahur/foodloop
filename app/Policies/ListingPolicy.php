<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

/**
 * Who may do what with a listing. Use it in controllers with
 * `$this->authorize('update', $listing)` or `Gate::authorize(...)`, and in
 * Blade with `@can('publish', $listing)`.
 *
 * The rule in one line: a seller can only touch their own shop's listings,
 * and only an approved shop can publish.
 */
class ListingPolicy
{
    /** Anyone, signed in or not, can browse the deals. */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Published listings (live, sold out, closed) are public, so an old link
     * shows "sold out" or "ended" instead of an error. Drafts and paused
     * listings are only for their shop and admins.
     */
    public function view(?User $user, Listing $listing): bool
    {
        return $listing->isPublished()
            || $user?->isAdmin()
            || $this->owns($user, $listing);
    }

    /** A seller with a shop profile can write listings (drafts, until approved). */
    public function create(User $user): bool
    {
        return $user->isSeller() && $user->seller !== null;
    }

    public function update(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    public function delete(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    /** Going live (or back live after a pause) needs an approved shop. */
    public function publish(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing) && $user->seller->isApproved();
    }

    public function pause(User $user, Listing $listing): bool
    {
        return $this->owns($user, $listing);
    }

    /** Admins can take a reported listing off the deals page (Stage 7). */
    public function moderate(User $user, Listing $listing): bool
    {
        return $user->isAdmin();
    }

    private function owns(?User $user, Listing $listing): bool
    {
        return $user !== null
            && $user->isSeller()
            && $user->seller !== null
            && $user->seller->id === $listing->seller_id;
    }
}
