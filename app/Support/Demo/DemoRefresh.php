<?php

namespace App\Support\Demo;

use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Notifications\InAppMessage;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * DEMO DATA ONLY. `php artisan demo:refresh`: fresh food, same people.
 *
 * KEEPS every account, every past order (collected, cancelled, no-show),
 * Aina's impact history, donations, reports, the shops waiting for approval,
 * strikes and blocks.
 *
 * RESETS the food:
 *   1. Every reservation still waiting to be collected is deleted, with the
 *      "You're booked" / "New reservation" messages about it. It wasn't
 *      history yet, and nobody may get a no-show strike for a deal that this
 *      command took away.
 *   2. Every listing that isn't closed yet (draft, live, sold out, paused)
 *      is closed, and its "left" number is put right. No leftovers are
 *      offered to the food bank for them (see step 3 in run()).
 *   3. A fresh set of deals is posted (DemoDeals), counted from now.
 *
 * Safe to run as often as you like. `php artisan migrate:fresh --seed` is
 * still the full wipe.
 */
class DemoRefresh
{
    /**
     * @return array{closed: int, removed: int, posted: int}
     */
    public function run(DemoDeals $deals): array
    {
        return DB::transaction(function () use ($deals) {
            $touched = collect();

            // 1. Waiting reservations go, wherever they are (also on a listing
            //    that closed a few minutes ago and is still in its grace period).
            $waiting = Reservation::query()->where('status', ReservationStatus::Reserved)->with('listing.seller')->get();

            $this->deleteMessagesAbout($waiting);
            Reservation::query()->whereKey($waiting->modelKeys())->delete();
            $touched = $touched->merge($waiting->pluck('listing_id'));

            // 2. Close what is still open.
            $open = Listing::query()
                ->whereIn('status', [ListingStatus::Draft, ListingStatus::Live, ListingStatus::SoldOut, ListingStatus::Paused])
                ->pluck('id');
            $touched = $touched->merge($open)->unique();

            Listing::query()->whereKey($touched)->with('donations')->get()->each(function (Listing $listing) use ($open) {
                if ($open->contains($listing->id)) {
                    $listing->status = ListingStatus::Closed;
                    $listing->closed_at = now();
                }

                // 3. The every-minute `listings:close` command offers leftovers of
                //    closed listings to the food bank when the seller ticked that
                //    box. These were not left over at closing time, they were
                //    taken away by the refresh: untick it, unless an offer was
                //    already made.
                if ($listing->donations->isEmpty()) {
                    $listing->offer_leftovers = false;
                }

                // Stock adds up again: left = total − everything not cancelled.
                $taken = (int) $listing->reservations()->where('status', '!=', ReservationStatus::Cancelled)->sum('quantity');
                $listing->quantity_available = max(0, $listing->quantity_total - $taken);

                $listing->save();
            });

            // Today's "already happened" part is added once a day, not on every refresh.
            $earlierToday = ! Reservation::query()
                ->where('pickup_code', '6208')
                ->where('status', ReservationStatus::Collected)
                ->where('collected_at', '>=', now()->subHours(12))
                ->exists();

            DemoPhotos::copy();
            $posted = $deals->post($earlierToday);
            $deals->announce();

            return ['closed' => $open->count(), 'removed' => $waiting->count(), 'posted' => count($posted)];
        });
    }

    /**
     * Delete the inbox messages about reservations that are being removed:
     * the customer's (they link to /orders/{id}, which is about to be a dead
     * link) and the shop's "New reservation" written at the same moment.
     *
     * @param  Collection<int, Reservation>  $reservations
     */
    private function deleteMessagesAbout(Collection $reservations): void
    {
        if ($reservations->isEmpty()) {
            return;
        }

        $links = $reservations->mapWithKeys(fn (Reservation $r) => [route('orders.show', $r, absolute: false) => true]);

        $shopMessages = $reservations->map(fn (Reservation $r) => [
            'user' => $r->listing->seller->user_id,
            'title' => 'New reservation: '.$r->quantity.' × '.$r->listing->title,
            'at' => $r->created_at,
        ]);

        DatabaseNotification::query()
            ->where('type', InAppMessage::class)
            ->get()
            ->filter(function (DatabaseNotification $message) use ($links, $shopMessages) {
                if ($links->has($message->data['url'] ?? '')) {
                    return true;
                }

                return ($message->data['kind'] ?? '') === 'seller.reservation'
                    && $shopMessages->contains(fn (array $about) => (int) $message->notifiable_id === $about['user']
                        && $message->data['title'] === $about['title']
                        && abs($message->created_at->diffInSeconds($about['at'])) <= 5);
            })
            ->each->delete();
    }
}
