<?php

namespace App\Services;

use App\Enums\DonationStatus;
use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\SellerActionRefused;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Everything a seller does to their own listings: write, publish, pause,
 * resume, delete a draft, and offer leftovers to the food bank.
 *
 * Who may do it is checked by App\Policies\ListingPolicy in the controller.
 * This class holds the rules about WHEN it may happen. Stock and
 * reservations are only ever changed by App\Services\ReservationService.
 */
class SellerListingService
{
    /**
     * Fields that can't change once someone has reserved (owner's decision,
     * 14 Nov): a student never pays a different price, finds fewer portions
     * or turns up at a moved time. Photo, text, allergens and pausing stay open.
     */
    public const LOCKED_FIELDS = [
        'original_price_sen', 'price_sen', 'quantity_total', 'pickup_start', 'pickup_end', 'halal_status',
    ];

    /** Someone holds (or held) part of this listing: any reservation that wasn't cancelled. */
    public function isLocked(Listing $listing): bool
    {
        return $listing->exists
            && $listing->reservations()->where('status', '!=', ReservationStatus::Cancelled)->exists();
    }

    /** How many portions are reserved or collected (not available, not cancelled). */
    public function takenCount(Listing $listing): int
    {
        return (int) $listing->reservations()
            ->whereIn('status', [ReservationStatus::Reserved, ReservationStatus::Collected])
            ->sum('quantity');
    }

    /**
     * Create a listing (as a draft) or update one, and optionally publish it.
     *
     * @param  array<string, mixed>  $data  validated by App\Http\Requests\Seller\SaveListingRequest
     *
     * @throws SellerActionRefused
     */
    public function save(Seller $seller, ?Listing $listing, array $data, ?UploadedFile $photo, bool $publish): Listing
    {
        $newPhotoPath = $photo?->store('listings', 'public');

        try {
            return DB::transaction(function () use ($seller, $listing, $data, $newPhotoPath, $publish) {
                if ($listing === null) {
                    $listing = new Listing(['status' => ListingStatus::Draft]);
                    $listing->seller()->associate($seller);
                } else {
                    $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
                }

                if ($listing->status === ListingStatus::Closed) {
                    throw new SellerActionRefused('This listing has closed, so it can’t be changed any more.');
                }

                $locked = $this->isLocked($listing);

                $listing->fill(collect($data)->only([
                    'type', 'title', 'description', 'category', 'allergens', 'offer_leftovers',
                ])->all());

                if (! $locked) {
                    $listing->fill(collect($data)->only(self::LOCKED_FIELDS)->all());
                    // Nobody holds any portion yet, so all of them are available.
                    $listing->quantity_available = $listing->quantity_total;
                }

                $oldPhotoPath = $listing->photo_path;
                if ($newPhotoPath !== null) {
                    $listing->photo_path = $newPhotoPath;
                }

                if ($publish && in_array($listing->status, [ListingStatus::Draft, ListingStatus::Paused], true)) {
                    $this->goLive($listing);
                }

                $listing->save();

                if ($newPhotoPath !== null && $this->isUpload($oldPhotoPath)) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldPhotoPath));
                }

                return $listing;
            });
        } catch (\Throwable $e) {
            // Nothing was saved, so the new picture isn't used by anything.
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $e;
        }
    }

    /**
     * Put a draft or paused listing on the deals page.
     *
     * @throws SellerActionRefused
     */
    public function publish(Listing $listing): Listing
    {
        return DB::transaction(function () use ($listing) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if (! in_array($listing->status, [ListingStatus::Draft, ListingStatus::Paused], true)) {
                throw new SellerActionRefused('Only a draft or a paused listing can be put live.');
            }

            $this->goLive($listing);
            $listing->save();

            return $listing;
        });
    }

    /**
     * Take a live (or sold-out) listing off the deals page. Reservations
     * already made stay valid; nobody new can reserve.
     *
     * @throws SellerActionRefused
     */
    public function pause(Listing $listing): Listing
    {
        return DB::transaction(function () use ($listing) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if (! in_array($listing->status, [ListingStatus::Live, ListingStatus::SoldOut], true)) {
                throw new SellerActionRefused('Only a live listing can be paused.');
            }

            $listing->status = ListingStatus::Paused;
            $listing->save();

            return $listing;
        });
    }

    /**
     * Delete a listing that was never published. Anything that has been
     * live stays, so the order history and the impact numbers add up.
     *
     * @throws SellerActionRefused
     */
    public function deleteDraft(Listing $listing): void
    {
        DB::transaction(function () use ($listing) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());

            if ($listing->status !== ListingStatus::Draft || $listing->reservations()->exists()) {
                throw new SellerActionRefused('Only a draft that was never published can be deleted. Pause it instead.');
            }

            $path = $listing->photo_path;
            $listing->delete();

            if ($this->isUpload($path)) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }
        });
    }

    /**
     * The seller's "Offer to food bank" button, for a closed listing whose
     * leftovers weren't offered automatically (the box wasn't ticked).
     *
     * @throws SellerActionRefused
     */
    public function offerLeftovers(Listing $listing): Donation
    {
        return DB::transaction(function () use ($listing) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $partner = FoodBankPartner::query()->orderBy('id')->first();

            if ($listing->status !== ListingStatus::Closed) {
                throw new SellerActionRefused('Leftovers can be offered once the listing has closed.');
            }
            if ($listing->reservations()->where('status', ReservationStatus::Reserved)->exists()) {
                throw new SellerActionRefused('Wait until every customer has collected or missed their pickup.');
            }
            if ($listing->donations()->exists()) {
                throw new SellerActionRefused('These leftovers have already been offered.');
            }
            if ($listing->leftovers() < 1) {
                throw new SellerActionRefused('Nothing is left over from this listing.');
            }
            if ($partner === null) {
                throw new SellerActionRefused('No food bank partner is set up yet.');
            }

            $donation = new Donation(['quantity' => $listing->leftovers(), 'status' => DonationStatus::Offered]);
            $donation->listing()->associate($listing);
            $donation->partner()->associate($partner);
            $donation->save();

            return $donation;
        });
    }

    /** @throws SellerActionRefused */
    private function goLive(Listing $listing): void
    {
        if (! $listing->seller->isApproved()) {
            throw new SellerActionRefused('Your shop has to be approved by an admin before listings can go live.');
        }
        if ($listing->reservationsCloseAt()->isPast()) {
            throw new SellerActionRefused('The pickup time has already started. Choose a later pickup time first.');
        }

        $listing->status = $listing->quantity_available > 0 ? ListingStatus::Live : ListingStatus::SoldOut;
        $listing->published_at ??= now();
    }

    /** A photo a seller uploaded (not one of the demo pictures). */
    private function isUpload(?string $path): bool
    {
        return $path !== null && str_starts_with($path, 'listings/') && ! str_starts_with($path, 'listings/demo/');
    }
}
