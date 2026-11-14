<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Exceptions\ReservationRefused;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The only place where a reservation changes status or a listing's stock
 * changes (docs/BUILD_PLAN.md §5). Controllers call these methods and show
 * the refusal message; they never touch status or stock themselves.
 *
 *   reserved ─ collect() ──▶ collected
 *      ├───── cancel() ───▶ cancelled (late = +1 strike), stock returned, pay-now refunded
 *      └───── markNoShow() ▶ no_show (+1 strike), stock becomes leftovers
 *
 * Every method runs in a database transaction and locks the rows it reads
 * before deciding, always in the same order (listing, seller, reservation,
 * user) so two requests can never deadlock each other or both win.
 *
 * Who is allowed to call what (a customer cancelling only their own
 * reservation, a seller collecting only at their own shop) is checked by
 * the policies and controllers in later stages.
 */
class ReservationService
{
    /** How many times a transaction is retried if the database reports a deadlock. */
    private const ATTEMPTS = 3;

    /**
     * Reserve portions of a listing for a customer.
     *
     * @throws ReservationRefused
     */
    public function reserve(User $customer, Listing $listing, int $quantity, PaymentMethod $paymentMethod): Reservation
    {
        return DB::transaction(function () use ($customer, $listing, $quantity, $paymentMethod) {
            // Lock the listing row first: a second request for the same
            // listing waits here until this one has finished, then sees the
            // new stock. This is what stops two people buying the last item.
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            // Locking the shop keeps pickup codes unique across its listings.
            $seller = Seller::query()->lockForUpdate()->findOrFail($listing->seller_id);
            $customer = User::query()->findOrFail($customer->getKey());
            $now = now();

            $this->refuseIf($seller->user_id === $customer->id, RefusalReason::OwnListing);
            $this->refuseIf($customer->isBlocked(), RefusalReason::CustomerBlocked);
            $this->refuseIf(! $seller->isApproved(), RefusalReason::SellerNotApproved);
            $this->refuseIf($listing->status === ListingStatus::SoldOut, RefusalReason::SoldOut);
            $this->refuseIf($listing->status !== ListingStatus::Live, RefusalReason::ListingNotLive);
            $this->refuseIf($listing->pickup_end->lessThanOrEqualTo($now), RefusalReason::WindowEnded);
            $this->refuseIf($quantity < 1, RefusalReason::InvalidQuantity);
            $this->refuseIf($quantity > config('foodloop.max_qty_per_listing'), RefusalReason::OverMaxQuantity);
            $this->refuseIf(
                $quantity + $this->quantityHeld($customer, $listing) > config('foodloop.max_qty_per_listing'),
                RefusalReason::OverListingLimit,
            );
            $this->refuseIf($quantity > $listing->quantity_available, RefusalReason::NotEnoughStock);

            $listing->quantity_available -= $quantity;

            if ($listing->quantity_available === 0) {
                $listing->status = ListingStatus::SoldOut;
            }

            $listing->save();

            $reservation = new Reservation([
                'quantity' => $quantity,
                'total_sen' => $listing->price_sen * $quantity,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === PaymentMethod::PayNow
                    ? PaymentStatus::SimulatedPaid
                    : PaymentStatus::Unpaid,
                'pickup_code' => $this->newPickupCode($seller),
                'status' => ReservationStatus::Reserved,
                'expires_at' => $listing->pickup_end->copy()->addMinutes(config('foodloop.grace_minutes')),
            ]);
            $reservation->listing()->associate($listing);
            $reservation->customer()->associate($customer);
            $reservation->save();

            return $reservation;
        }, self::ATTEMPTS);
    }

    /**
     * Cancel a reservation and put its portions back on sale. A pay-now
     * reservation is refunded (simulated).
     *
     * Free until `free_cancel_minutes` before the pickup window starts, and
     * always free within `change_of_mind_minutes` of reserving. Any later
     * and it is a late cancel, which counts as a no-show strike.
     *
     * @throws ReservationRefused
     */
    public function cancel(Reservation $reservation): Reservation
    {
        [$reservation, $nowBlocked] = DB::transaction(
            fn () => $this->cancelLocked($reservation, byCustomer: true),
            self::ATTEMPTS,
        );

        if ($nowBlocked) {
            $this->cancelWaitingReservationsOf($reservation->customer_id);
        }

        return $reservation;
    }

    /**
     * The seller types the customer's 4-digit code at pickup. Only codes of
     * reservations still waiting at THIS seller's shop are found, and only
     * from the start of the pickup window until the grace period ends.
     *
     * @throws ReservationRefused
     */
    public function collect(Seller $seller, string $code): Reservation
    {
        return DB::transaction(function () use ($seller, $code) {
            $reservation = Reservation::query()
                ->where('status', ReservationStatus::Reserved)
                ->where('pickup_code', trim($code))
                ->whereHas('listing', fn ($query) => $query->where('seller_id', $seller->getKey()))
                ->lockForUpdate()
                ->first();

            $this->refuseIf($reservation === null, RefusalReason::CodeNotFound);

            $now = now();

            $this->refuseIf($now->lessThan($reservation->listing->pickup_start), RefusalReason::TooEarly);
            $this->refuseIf($now->greaterThan($reservation->expires_at), RefusalReason::TooLate);

            $reservation->status = ReservationStatus::Collected;
            $reservation->collected_at = $now;
            // Pay-at-pickup reservations are paid at this moment (simulated).
            $reservation->payment_status = PaymentStatus::SimulatedPaid;
            $reservation->save();

            return $reservation;
        }, self::ATTEMPTS);
    }

    /**
     * Turn an uncollected reservation into a no-show once its grace period
     * is over. The customer gets a strike. The portions are NOT put back on
     * sale (the window is over); they count as the listing's leftovers.
     *
     * @throws ReservationRefused
     */
    public function markNoShow(Reservation $reservation): Reservation
    {
        [$reservation, $nowBlocked] = DB::transaction(function () use ($reservation) {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());
            $now = now();

            $this->refuseIf($reservation->status !== ReservationStatus::Reserved, RefusalReason::NotReserved);
            $this->refuseIf($now->lessThanOrEqualTo($reservation->expires_at), RefusalReason::NotExpiredYet);

            $reservation->status = ReservationStatus::NoShow;
            $reservation->save();

            return [$reservation, $this->addStrike($reservation->customer_id, $now)];
        }, self::ATTEMPTS);

        if ($nowBlocked) {
            $this->cancelWaitingReservationsOf($reservation->customer_id);
        }

        return $reservation;
    }

    /**
     * A blocked customer does not keep the reservations they already made:
     * every one still waiting is cancelled, its portions go back on sale and
     * pay-now ones are refunded (simulated). These cancels are not the
     * customer's doing, so they are never "late" and add no strikes.
     *
     * Runs right after a block starts, and again from `reservations:expire`
     * as a safety net. Returns how many were cancelled.
     */
    public function cancelWaitingReservationsOf(int $customerId): int
    {
        $cancelled = 0;

        Reservation::query()
            ->where('customer_id', $customerId)
            ->where('status', ReservationStatus::Reserved)
            ->orderBy('id')
            ->get()
            ->each(function (Reservation $reservation) use (&$cancelled) {
                try {
                    DB::transaction(fn () => $this->cancelLocked($reservation, byCustomer: false), self::ATTEMPTS);
                    $cancelled++;
                } catch (ReservationRefused) {
                    // Collected a moment ago, or already past its grace
                    // period (then it becomes a no-show instead).
                }
            });

        return $cancelled;
    }

    /**
     * Cancel the waiting reservations of every customer who is blocked right
     * now. Normally there are none, because a block cancels them when it
     * starts; this catches anything left behind. Safe to run again and again.
     */
    public function releaseBlockedCustomers(): int
    {
        return (int) Reservation::query()
            ->where('status', ReservationStatus::Reserved)
            ->whereHas('customer', fn ($query) => $query->where('blocked_until', '>', now()))
            ->distinct()
            ->pluck('customer_id')
            ->sum(fn (int $customerId) => $this->cancelWaitingReservationsOf($customerId));
    }

    /**
     * Mark every reservation whose grace period has passed as a no-show.
     * Used by the `reservations:expire` command. Returns how many changed;
     * running it again straight away changes nothing.
     */
    public function expireOverdue(): int
    {
        $expired = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Reserved)
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->get()
            ->each(function (Reservation $reservation) use (&$expired) {
                try {
                    $this->markNoShow($reservation);
                    $expired++;
                } catch (ReservationRefused) {
                    // Collected or cancelled a moment ago: nothing to do.
                }
            });

        return $expired;
    }

    /**
     * The cancel itself. Must run inside a transaction. `$byCustomer` false
     * means the system is cancelling (customer got blocked): never late.
     *
     * @return array{0: Reservation, 1: bool} the reservation, and whether this cancel got the customer blocked
     */
    private function cancelLocked(Reservation $reservation, bool $byCustomer): array
    {
        $listing = Listing::query()->lockForUpdate()->findOrFail($reservation->listing_id);
        $seller = Seller::query()->findOrFail($listing->seller_id);
        $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->getKey());
        $now = now();

        $this->refuseIf($reservation->status !== ReservationStatus::Reserved, RefusalReason::NotReserved);
        $this->refuseIf($now->greaterThan($reservation->expires_at), RefusalReason::AlreadyExpired);

        $changedMindUntil = $reservation->created_at->copy()->addMinutes(config('foodloop.change_of_mind_minutes'));

        $late = $byCustomer
            && $now->greaterThan($listing->freeCancelUntil())
            && $now->greaterThan($changedMindUntil);

        $reservation->status = ReservationStatus::Cancelled;
        $reservation->cancelled_at = $now;
        $reservation->late_cancel = $late;

        if ($reservation->payment_status === PaymentStatus::SimulatedPaid) {
            $reservation->payment_status = PaymentStatus::Refunded;
        }

        $reservation->save();

        $listing->quantity_available += $reservation->quantity;

        // A sold-out listing goes back on sale, if it still can be sold.
        if ($listing->status === ListingStatus::SoldOut
            && $listing->pickup_end->greaterThan($now)
            && $seller->isApproved()) {
            $listing->status = ListingStatus::Live;
        }

        $listing->save();

        return [$reservation, $late && $this->addStrike($reservation->customer_id, $now)];
    }

    /**
     * How many portions of this listing the customer already holds:
     * everything they reserved on it that was not cancelled.
     */
    private function quantityHeld(User $customer, Listing $listing): int
    {
        return (int) Reservation::query()
            ->where('customer_id', $customer->getKey())
            ->where('listing_id', $listing->getKey())
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->sum('quantity');
    }

    /**
     * One more strike. At `strikes_to_block` the user is blocked from
     * reserving for `block_days` and the count starts again from zero.
     * Returns true when this strike started (or renewed) a block.
     */
    private function addStrike(int $userId, Carbon $now): bool
    {
        $user = User::query()->lockForUpdate()->findOrFail($userId);

        $strikes = $user->no_show_strikes + 1;

        if ($strikes < config('foodloop.strikes_to_block')) {
            $user->forceFill(['no_show_strikes' => $strikes])->save();

            return false;
        }

        $blockedUntil = $now->copy()->addDays(config('foodloop.block_days'));

        // Never shorten a block that is already running.
        if ($user->blocked_until?->greaterThan($blockedUntil)) {
            $blockedUntil = $user->blocked_until;
        }

        $user->forceFill(['no_show_strikes' => 0, 'blocked_until' => $blockedUntil])->save();

        return true;
    }

    /**
     * Four random digits that no other waiting reservation at this shop
     * has, so the seller can find the reservation by code alone.
     */
    private function newPickupCode(Seller $seller): string
    {
        $inUse = Reservation::query()
            ->where('status', ReservationStatus::Reserved)
            ->whereHas('listing', fn ($query) => $query->where('seller_id', $seller->getKey()))
            ->pluck('pickup_code')
            ->flip();

        if ($inUse->count() >= 10000) {
            throw new RuntimeException('This shop has used every pickup code. Collect or expire some reservations first.');
        }

        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($inUse->has($code));

        return $code;
    }

    private function refuseIf(bool $condition, RefusalReason $reason): void
    {
        if ($condition) {
            throw new ReservationRefused($reason);
        }
    }
}
