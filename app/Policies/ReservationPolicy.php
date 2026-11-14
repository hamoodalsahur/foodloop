<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

/**
 * Who may do what with a reservation. The reservation engine
 * (App\Services\ReservationService) decides whether the action is possible
 * right now; this decides whether this person may ask at all.
 */
class ReservationPolicy
{
    /** Only customer accounts reserve food. */
    public function create(User $user): bool
    {
        return $user->isCustomer();
    }

    /** The customer who made it, the shop it is for, and admins. */
    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->customer_id === $user->id
            || $this->isAtUsersShop($user, $reservation)
            || $user->isAdmin();
    }

    /** Only the customer who made it can cancel it. */
    public function cancel(User $user, Reservation $reservation): bool
    {
        return $reservation->customer_id === $user->id;
    }

    /** Only the shop the reservation is for can mark it collected. */
    public function collect(User $user, Reservation $reservation): bool
    {
        return $this->isAtUsersShop($user, $reservation);
    }

    private function isAtUsersShop(User $user, Reservation $reservation): bool
    {
        return $user->isSeller()
            && $user->seller !== null
            && $user->seller->id === $reservation->listing->seller_id;
    }
}
