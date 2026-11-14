<?php

namespace App\Http\Controllers;

use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Developer-only preview pages (APP_ENV=local): every design-system
 * component and both layouts with SAMPLE DATA that is built in memory.
 * Nothing here reads or writes the database, so it works before seeding.
 *
 *   /dev/components          every component (docs/UI_GUIDE.md)
 *   /dev/layouts/customer    the customer layout, laid out like mockup c01
 *   /dev/layouts/dashboard   the dashboard layout, laid out like mockup c08
 */
class DevPreviewController extends Controller
{
    public function components(): View
    {
        return view('dev.components', [
            'listings' => $this->sampleListings(),
            'statuses' => ReservationStatus::cases(),
        ]);
    }

    public function customerLayout(): View
    {
        return view('dev.layout-customer', ['listings' => $this->sampleListings()]);
    }

    public function dashboardLayout(): View
    {
        $listings = $this->sampleListings();

        return view('dev.layout-dashboard', [
            'shop' => $listings['bundle']->seller,
            'bundle' => $listings['bundle'],
            'croissants' => $listings['croissants'],
            'pickups' => [
                ['4721', 'Aina R.', 'Bakery Surprise Bundle', 1, ReservationStatus::Reserved],
                ['3390', 'Hafiz M.', 'Bakery Surprise Bundle', 1, ReservationStatus::Reserved],
                ['6208', 'Priya S.', 'Butter croissants × 3', 1, ReservationStatus::Collected],
                ['1185', 'Mei Ling T.', 'Butter croissants × 3', 2, ReservationStatus::Collected],
                ['9052', 'Daniel K.', 'Butter croissants × 3', 1, ReservationStatus::NoShow],
            ],
        ]);
    }

    /**
     * The mockup listings as unsaved models.
     *
     * @return Collection<string, Listing>
     */
    private function sampleListings(): Collection
    {
        $shop = fn (string $name, HalalStatus $halal = HalalStatus::MuslimOwned) => (new Seller)->forceFill([
            'business_name' => $name,
            'halal_claim' => $halal,
            'status' => SellerStatus::Approved,
        ]);

        // Use the demo picture if it has been copied by the seeder; otherwise show the placeholder.
        $photo = fn (string $file) => Storage::disk('public')->exists("listings/demo/{$file}") ? "listings/demo/{$file}" : null;

        $listing = fn (Seller $seller, array $attributes) => (new Listing)->forceFill([
            'type' => ListingType::Regular,
            'status' => ListingStatus::Live,
            'halal_status' => $seller->halal_claim,
            'quantity_total' => 5,
            'quantity_available' => 3,
            'pickup_start' => now()->subHour(),
            ...$attributes,
        ])->setRelation('seller', $seller);

        $rotiLane = $shop('Roti Lane Bakery', HalalStatus::Certified);
        $dailyGrind = $shop('Daily Grind Café');

        return collect([
            'puffs' => $listing($shop('Mak Long’s Kuih'), [
                'title' => 'Curry puffs × 4', 'photo_path' => $photo('curry-puffs.svg'),
                'price_sen' => 300, 'original_price_sen' => 600, 'pickup_end' => now()->addMinutes(18),
            ]),
            'latte' => $listing($dailyGrind, [
                'title' => 'Iced latte', 'photo_path' => $photo('iced-latte.svg'),
                'price_sen' => 450, 'original_price_sen' => 900, 'pickup_end' => now()->addMinutes(33),
            ]),
            'bundle' => $listing($rotiLane, [
                'type' => ListingType::Bundle,
                'title' => 'Bakery Surprise Bundle', 'photo_path' => $photo('bakery-bundle.svg'),
                'description' => '4–6 of today’s unsold breads, buns and pastries. You’ll find out what’s inside at pickup.',
                'price_sen' => 800, 'original_price_sen' => 2000, 'allergens' => ['egg', 'milk', 'wheat'],
                'pickup_start' => now()->addMinutes(42), 'pickup_end' => now()->addMinutes(162),
            ]),
            'nasiayam' => $listing($shop('Kafe Seri Ilmu'), [
                'title' => 'Nasi ayam set (2 pax) with soup, sambal and a very long name', 'photo_path' => null,
                'price_sen' => 650, 'original_price_sen' => 1300, 'pickup_end' => now()->addMinutes(58),
            ]),
            'croissants' => $listing($rotiLane, [
                'type' => ListingType::Flash,
                'title' => 'Butter croissants × 3', 'photo_path' => $photo('croissants.svg'),
                'price_sen' => 600, 'original_price_sen' => 1200, 'quantity_total' => 4, 'quantity_available' => 0,
                'status' => ListingStatus::SoldOut, 'pickup_end' => now()->addMinutes(75),
            ]),
        ]);
    }
}
