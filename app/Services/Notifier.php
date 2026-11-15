<?php

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Donation;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Notifications\InAppMessage;
use App\Queries\CustomerImpact;
use App\Support\CustomerName;
use App\Support\Money;
use App\Support\PickupWindow;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Every notification FoodLoop sends, and its exact wording, in one place.
 * To change what a message says, change it here.
 *
 * Messages go to the person's in-app inbox (the bell) only. The prototype
 * sends no email, push or SMS (docs/BUILD_PLAN.md "Out of scope").
 *
 * The services call these methods AFTER their database work has been saved.
 * A message that can't be written never breaks the action it is about: the
 * problem is logged and the reservation, approval, etc. still stands.
 *
 * Each method hands send() a short machine name (used by tests) and a
 * function returning [who gets it, title, body, where a tap goes].
 */
class Notifier
{
    /*
    |--------------------------------------------------------------------------
    | To customers
    |--------------------------------------------------------------------------
    */

    public function reservationBooked(Reservation $reservation): void
    {
        $this->send('reservation.booked', function () use ($reservation) {
            $listing = $reservation->listing;
            $payment = $reservation->payment_method === PaymentMethod::PayNow
                ? 'Paid (simulated).'
                : 'Pay '.Money::format($reservation->total_sen).' at pickup.';

            return [
                $reservation->customer,
                'You’re booked: '.$listing->title,
                'Pickup code '.$reservation->pickup_code.'. Collect at '.$listing->seller->business_name.', '
                    .PickupWindow::withDay($listing->pickup_start, $listing->pickup_end).'. '.$payment
                    .' Free to cancel until '.PickupWindow::time($reservation->freeCancelUntil()).'.',
                $this->path('orders.show', $reservation),
            ];
        });
    }

    public function reservationCollected(Reservation $reservation): void
    {
        $this->send('reservation.collected', fn () => [
            $reservation->customer,
            'Collected: '.$reservation->listing->title,
            'Thanks for rescuing '.$this->portions($reservation->quantity).'. You saved '
                .Money::format(CustomerImpact::savingSen($reservation)).' on this one.',
            $this->path('impact'),
        ]);
    }

    /** A missed pickup or a late cancel that did NOT (yet) lead to a limit. */
    public function strikeAdded(Reservation $reservation): void
    {
        $this->send('customer.strike', function () use ($reservation) {
            $customer = $reservation->customer->fresh();
            $limit = config('foodloop.strikes_to_block');

            return [
                $customer,
                ($reservation->late_cancel ? 'Late cancel' : 'Missed pickup').': strike '.$customer->no_show_strikes.' of '.$limit,
                ($reservation->late_cancel
                    ? 'You cancelled '.$reservation->listing->title.' after the free-cancel time.'
                    : 'You didn’t collect '.$reservation->listing->title.' in time.')
                    .' At '.$limit.' strikes, reserving is paused for '.config('foodloop.block_days').' days.',
                $this->path('orders.show', $reservation),
            ];
        });
    }

    /** The strike that started a limit. `$cancelled` = other reservations cancelled because of it. */
    public function customerBlocked(User $customer, int $cancelled): void
    {
        $this->send('customer.blocked', function () use ($customer, $cancelled) {
            $customer = $customer->fresh();

            return [
                $customer,
                'Reserving is paused until '.$customer->blocked_until->format('D j M'),
                'That was strike '.config('foodloop.strikes_to_block').'. You can reserve again in '
                    .config('foodloop.block_days').' days.'
                    .($cancelled > 0
                        ? ' '.$this->count($cancelled, 'reservation you still had was', 'reservations you still had were')
                            .' cancelled, and anything paid was refunded (simulated).'
                        : ''),
                $this->path('orders.index'),
            ];
        });
    }

    /** An admin took the listing down, so the customer's reservation was cancelled for them. */
    public function reservationCancelledByFoodLoop(Reservation $reservation): void
    {
        $this->send('reservation.cancelled_by_foodloop', fn () => [
            $reservation->customer,
            'Reservation cancelled: '.$reservation->listing->title,
            'FoodLoop took this listing down after a report, so your pickup is off. '
                .($reservation->payment_method === PaymentMethod::PayNow
                    ? 'Your payment was refunded (simulated).'
                    : 'You haven’t been charged.')
                .' This doesn’t count as a strike.',
            $this->path('orders.show', $reservation),
        ]);
    }

    public function limitLifted(User $customer): void
    {
        $this->send('customer.limit_lifted', fn () => [
            $customer,
            'You can reserve again',
            'An admin cleared your strikes and lifted your limit. See what’s on tonight.',
            $this->path('deals.index'),
        ]);
    }

    /**
     * A listing went live for the first time. Customers who follow the shop
     * ("Notify me next time") get one message; everyone else with deal
     * alerts on gets another. Nobody gets both.
     */
    public function newListing(Listing $listing): void
    {
        $about = fn () => Money::format($listing->price_sen).' instead of '.Money::format($listing->original_price_sen)
            .', '.$this->portions($listing->quantity_available).' left. Pickup '
            .PickupWindow::withDay($listing->pickup_start, $listing->pickup_end).'.';

        $canReserve = fn ($query) => $query
            ->where('role', UserRole::Customer)
            ->where('users.id', '!=', $listing->seller->user_id)
            ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '<=', now()));

        $followerIds = [];

        $this->send('listing.followed_shop', function () use ($listing, $about, $canReserve, &$followerIds) {
            $followers = $listing->seller->followers()->tap($canReserve)->get();
            $followerIds = $followers->modelKeys();

            return [
                $followers,
                $listing->seller->business_name.' just posted: '.$listing->title,
                $about(),
                $this->path('listings.show', $listing),
            ];
        });

        $this->send('listing.deal_alert', fn () => [
            User::query()->where('deal_alerts', true)->tap($canReserve)->whereKeyNot($followerIds)->get(),
            'New deal: '.$listing->title,
            $listing->seller->business_name.' · '.$about(),
            $this->path('listings.show', $listing),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | To sellers
    |--------------------------------------------------------------------------
    */

    public function newReservation(Reservation $reservation): void
    {
        $this->send('seller.reservation', fn () => [
            $reservation->listing->seller->user,
            'New reservation: '.$reservation->quantity.' × '.$reservation->listing->title,
            CustomerName::short($reservation->customer->name).' · '
                .($reservation->payment_method === PaymentMethod::PayNow
                    ? 'paid (simulated)'
                    : 'pays '.Money::format($reservation->total_sen).' at pickup')
                .' · '.$this->portions($reservation->listing->quantity_available).' left.',
            $this->path('seller.pickups.index'),
        ]);
    }

    public function listingSoldOut(Listing $listing): void
    {
        $this->send('seller.sold_out', fn () => [
            $listing->seller->user,
            'Sold out: '.$listing->title,
            'All '.$this->portions($listing->quantity_total).' are reserved. Pickup '
                .PickupWindow::withDay($listing->pickup_start, $listing->pickup_end).'.',
            $this->path('seller.pickups.index'),
        ]);
    }

    /** A reservation was cancelled (by the customer, or because the customer got limited). */
    public function reservationCancelled(Reservation $reservation): void
    {
        $this->send('seller.cancellation', fn () => [
            $reservation->listing->seller->user,
            'Reservation cancelled: '.$reservation->quantity.' × '.$reservation->listing->title,
            CustomerName::short($reservation->customer->name).' won’t be coming. '
                .ucfirst($this->count($reservation->quantity, 'portion is', 'portions are')).' back on sale.',
            $this->path('seller.listings.index'),
        ]);
    }

    public function noShow(Reservation $reservation): void
    {
        $this->send('seller.no_show', fn () => [
            $reservation->listing->seller->user,
            'No-show: '.$reservation->quantity.' × '.$reservation->listing->title,
            CustomerName::short($reservation->customer->name).' didn’t collect in time. '
                .ucfirst($this->count($reservation->quantity, 'portion counts', 'portions count')).' as leftovers.',
            $this->path('seller.donations.index'),
        ]);
    }

    /** An admin marked the order collected (the override), so the shop knows it wasn't them. */
    public function collectedByAdmin(Reservation $reservation): void
    {
        $this->send('seller.collected_by_admin', fn () => [
            $reservation->listing->seller->user,
            'FoodLoop marked code '.$reservation->pickup_code.' collected',
            'An admin marked '.$reservation->quantity.' × '.$reservation->listing->title.' for '
                .CustomerName::short($reservation->customer->name).' as collected. Nothing for you to do.',
            $this->path('seller.pickups.index'),
        ]);
    }

    public function shopApproved(Seller $seller): void
    {
        $this->send('seller.approved', fn () => [
            $seller->user,
            'Your shop is approved',
            $seller->business_name.' is verified on FoodLoop. You can put your listings live now.',
            $this->path('seller.listings.index'),
        ]);
    }

    public function shopRejected(Seller $seller): void
    {
        $this->send('seller.rejected', fn () => [
            $seller->user,
            'Your shop wasn’t approved',
            'An admin looked at '.$seller->business_name.' and couldn’t approve it. Check your SSM number and halal details, then contact FoodLoop.',
            $this->path('seller.profile.edit'),
        ]);
    }

    public function shopSuspended(Seller $seller): void
    {
        $this->send('seller.suspended', fn () => [
            $seller->user,
            'Your shop is suspended',
            'Your listings are off the deals page for now. Customers who already reserved can still collect.',
            $this->path('seller.dashboard'),
        ]);
    }

    /** The admin's "Ask for info" on a shop waiting for approval. */
    public function infoRequested(Seller $seller): void
    {
        $this->send('seller.info_requested', fn () => [
            $seller->user,
            'FoodLoop needs more info to approve your shop',
            '“'.$seller->info_request.'” Update your shop profile to answer.',
            $this->path('seller.profile.edit'),
        ]);
    }

    /** `$summary` is what the customers reported, without saying who they are. */
    public function sellerWarned(Listing $listing, string $summary): void
    {
        $this->send('seller.warning', fn () => [
            $listing->seller->user,
            'Warning about '.$listing->title,
            'Customers reported: '.$this->sentence($summary).' The listing stays up. Please fix it.',
            $this->path('seller.dashboard'),
        ]);
    }

    public function listingTakenDown(Listing $listing, string $summary, int $cancelled): void
    {
        $this->send('seller.takedown', fn () => [
            $listing->seller->user,
            'FoodLoop took down '.$listing->title,
            (filled($summary) ? 'Customers reported: '.$this->sentence($summary).' ' : 'An admin took this listing off FoodLoop. ')
                .($cancelled > 0
                    ? ucfirst($this->count($cancelled, 'reservation was', 'reservations were')).' cancelled for the customers.'
                    : 'Nobody had a reservation waiting.'),
            $this->path('seller.dashboard'),
        ]);
    }

    public function leftoversOffered(Donation $donation): void
    {
        $this->send('seller.leftovers_offered', fn () => [
            $donation->listing->seller->user,
            'Leftovers offered: '.$donation->quantity.' × '.$donation->listing->title,
            'Offered to '.$donation->partner->name.'. You’ll hear here when they collect.',
            $this->path('seller.donations.index'),
        ]);
    }

    /** An admin recorded that the food bank collected (or declined) the leftovers. */
    public function donationSettled(Donation $donation): void
    {
        $this->send('seller.donation_settled', fn () => [
            $donation->listing->seller->user,
            $donation->status === DonationStatus::Collected
                ? 'Food bank collected your leftovers'
                : 'Food bank couldn’t take your leftovers',
            $donation->quantity.' × '.$donation->listing->title.' · '.$donation->partner->name.'.',
            $this->path('seller.donations.index'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | To admins
    |--------------------------------------------------------------------------
    */

    public function shopWaiting(Seller $seller): void
    {
        $this->send('admin.shop_waiting', fn () => [
            $this->admins(),
            'New shop to approve: '.$seller->business_name,
            'SSM '.($seller->ssm_no ?: 'missing').' · '.$seller->halal_claim->label().'.',
            $this->path('admin.sellers.index'),
        ]);
    }

    /** A shop that was asked for info has saved its profile again. */
    public function shopAnswered(Seller $seller): void
    {
        $this->send('admin.shop_answered', fn () => [
            $this->admins(),
            $seller->business_name.' updated its details',
            'You asked this shop for more info. Have another look and decide.',
            $this->path('admin.sellers.index'),
        ]);
    }

    public function listingReported(Report $report): void
    {
        $this->send('admin.report', fn () => [
            $this->admins(),
            'New report: '.$report->listing->title,
            $report->listing->seller->business_name.' · '.$report->reason->label()
                .(filled($report->details) ? ': “'.$report->details.'”' : '.'),
            $this->path('admin.reports.index'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Closure(): array{0: User|iterable<User>|null, 1: string, 2: string, 3: string|null}  $message
     */
    private function send(string $kind, Closure $message): void
    {
        try {
            [$to, $title, $body, $url] = $message();

            $to = $to instanceof User ? [$to] : collect($to)->all();

            if ($to !== []) {
                Notification::send($to, new InAppMessage($kind, $title, $body, $url));
            }
        } catch (Throwable $problem) {
            // Never let a message stop a reservation, an approval, etc.
            report($problem);
        }
    }

    /** @return Collection<int, User> */
    private function admins(): Collection
    {
        return User::query()->where('role', UserRole::Admin)->get();
    }

    /**
     * A path like "/orders/12", not a full address, so a message written by
     * the every-minute commands opens on whatever address the site runs at.
     */
    private function path(string $route, mixed $parameters = []): string
    {
        return route($route, $parameters, absolute: false);
    }

    /** Ends the text with a full stop unless it already ends a sentence or a quote. */
    private function sentence(string $text): string
    {
        return preg_match('/[.!?”]$/u', $text) ? $text : $text.'.';
    }

    /** "1 portion", "3 portions" */
    private function portions(int $count): string
    {
        return $this->count($count, 'portion', 'portions');
    }

    private function count(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }
}
