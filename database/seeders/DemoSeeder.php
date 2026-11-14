<?php

namespace Database\Seeders;

use App\Enums\HalalStatus;
use App\Enums\ListingCategory;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\ReportReason;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * DEMO DATA ONLY. Everything here is made up to match the approved mockups
 * (docs/mockups): the shops, people, prices, coordinates and order history are
 * not real offers or research results.
 *
 * Reset it any time with:  php artisan migrate:fresh --seed
 *
 * Pickup times are set relative to the moment you seed, so there are always
 * deals "closing soon" no matter what time of day you run it.
 */
class DemoSeeder extends Seeder
{
    /** Every demo account signs in with this password. */
    public const PASSWORD = 'password';

    /** A made-up "you are here" point; shop coordinates are offsets from it. */
    private const ORIGIN_LAT = 3.0690;

    private const ORIGIN_LNG = 101.5040;

    /** Roughly one kilometre in degrees at this latitude. */
    private const KM = 0.009;

    private CarbonImmutable $now;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder creates demo accounts with a known password. It must not run in production.');
        }

        if (User::where('email', 'admin@foodloop.test')->exists()) {
            $this->command?->warn('Demo data is already there. To reset it run: php artisan migrate:fresh --seed');

            return;
        }

        $this->now = CarbonImmutable::now()->startOfMinute();

        $this->copyDemoPhotos();

        FoodBankPartner::create([
            'name' => '[FOOD BANK PARTNER NAME]',
            'contact' => '[FOOD BANK CONTACT]',
        ]);

        $this->user('FoodLoop Admin (demo)', 'admin@foodloop.test')
            ->forceFill(['role' => 'admin'])->save();

        $customers = $this->customers();
        $shops = $this->approvedShops();
        $this->shopsWaitingForApproval();

        $live = $this->tonightsListings($shops);
        $this->tonightsReservations($live, $customers);
        $this->ainasOrderHistory($shops, $customers['aina']);

        Report::factory()->create([
            'reporter_id' => $customers['meiling']->id,
            'listing_id' => $live['sandwich']->id,
            'reason' => ReportReason::FoodQuality,
            'details' => 'Bread was stale at pickup.',
        ]);
    }

    /**
     * The drawn food pictures stand in for seller photos.
     */
    private function copyDemoPhotos(): void
    {
        foreach (glob(base_path('docs/demo-food/*.svg')) as $file) {
            Storage::disk('public')->put('listings/demo/'.basename($file), file_get_contents($file));
        }
    }

    private function user(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * @return array<string, User>
     */
    private function customers(): array
    {
        $customers = [
            'aina' => $this->user('Aina Rahman', 'aina@foodloop.test'),
            'hafiz' => $this->user('Hafiz Mansor', 'hafiz@foodloop.test'),
            'priya' => $this->user('Priya Selvam', 'priya@foodloop.test'),
            'meiling' => $this->user('Mei Ling Tan', 'meiling@foodloop.test'),
            'daniel' => $this->user('Daniel Khoo', 'daniel@foodloop.test'),
            'farah' => $this->user('Farah Nordin', 'farah@foodloop.test'),
            'jason' => $this->user('Jason Lim', 'jason@foodloop.test'),
        ];

        // The "No-show limits" box on the admin mockup. A block starts at the
        // third strike and the count restarts at 0, as in ReservationService.
        $customers['daniel']->forceFill(['no_show_strikes' => 0, 'blocked_until' => $this->now->addDays(5)])->save();
        $customers['farah']->forceFill(['no_show_strikes' => 0, 'blocked_until' => $this->now->addDays(2)])->save();
        $customers['jason']->forceFill(['no_show_strikes' => config('foodloop.strikes_to_block') - 1])->save();

        return $customers;
    }

    /**
     * The four shops on the customer mockups, already approved.
     *
     * @return array<string, Seller>
     */
    private function approvedShops(): array
    {
        Storage::disk('local')->put(
            'halal-certs/demo-roti-lane-bakery.txt',
            "DEMO DATA. Placeholder for an uploaded halal certificate. Not a real document.\n"
        );

        return [
            'rotilane' => $this->shop('Roti Lane Bakery', 'rotilane@foodloop.test', 0.6, 180, [
                'halal_claim' => HalalStatus::Certified,
                'halal_cert_path' => 'halal-certs/demo-roti-lane-bakery.txt',
            ])->approved()->create(),
            'kafeseriilmu' => $this->shop('Kafe Seri Ilmu', 'kafeseriilmu@foodloop.test', 0.3, 270)->approved()->create(),
            'dailygrind' => $this->shop('Daily Grind Café', 'dailygrind@foodloop.test', 0.9, 90)->approved()->create(),
            'maklong' => $this->shop('Mak Long’s Kuih', 'maklong@foodloop.test', 0.4, 0)->approved()->create(),
        ];
    }

    /**
     * The "Sellers to approve" table on the admin mockup.
     */
    private function shopsWaitingForApproval(): void
    {
        Storage::disk('local')->put(
            'halal-certs/demo-kedai-kopi-sinar.txt',
            "DEMO DATA. Placeholder for an uploaded halal certificate. Not a real document.\n"
        );

        $this->shop('Kedai Kopi Sinar', 'kedaikopisinar@foodloop.test', 0.8, 45, [
            'halal_claim' => HalalStatus::Certified,
            'halal_cert_path' => 'halal-certs/demo-kedai-kopi-sinar.txt',
            'created_at' => $this->now->subHours(2),
        ])->create();

        $this->shop('Dapur Bonda', 'dapurbonda@foodloop.test', 1.2, 135, [
            'created_at' => $this->now->subDay(),
        ])->create();

        // Says "certified" but uploaded nothing, and has no SSM number.
        $this->shop('Sweet Crumbs Bakehouse', 'sweetcrumbs@foodloop.test', 2.1, 225, [
            'ssm_no' => null,
            'halal_claim' => HalalStatus::Certified,
            'halal_cert_path' => null,
            'created_at' => $this->now->subDays(2),
        ])->create();
    }

    /**
     * A shop with its seller account, placed $km away from the origin point
     * in the compass direction $bearing (0 = north, 90 = east).
     *
     * @param  array<string, mixed>  $extra
     */
    private function shop(string $name, string $email, float $km, int $bearing, array $extra = [])
    {
        $owner = $this->user($name.' (demo seller)', $email);
        $owner->forceFill(['role' => 'seller'])->save();

        return Seller::factory()->state([
            'user_id' => $owner->id,
            'business_name' => $name,
            'ssm_no' => '[SSM NO.]',
            'phone' => '[PHONE]',
            'address' => '[SHOP ADDRESS]',
            'lat' => round(self::ORIGIN_LAT + cos(deg2rad($bearing)) * $km * self::KM, 7),
            'lng' => round(self::ORIGIN_LNG + sin(deg2rad($bearing)) * $km * self::KM, 7),
            'halal_claim' => HalalStatus::MuslimOwned,
            ...$extra,
        ]);
    }

    /**
     * What each shop sells in the mockups. Prices in sen (RM8 = 800).
     *
     * @return array<string, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            'bundle' => [
                'shop' => 'rotilane',
                'type' => ListingType::Bundle,
                'title' => 'Bakery Surprise Bundle',
                'description' => '4–6 of today’s unsold breads, buns and pastries. You’ll find out what’s inside at pickup.',
                'category' => ListingCategory::Bakery,
                'photo_path' => 'listings/demo/bakery-bundle.svg',
                'original_price_sen' => 2000,
                'price_sen' => 800,
                'halal_status' => HalalStatus::Certified,
                'allergens' => ['egg', 'milk', 'wheat'],
            ],
            'croissants' => [
                'shop' => 'rotilane',
                'type' => ListingType::Flash,
                'title' => 'Butter croissants × 3',
                'description' => 'Three butter croissants baked this morning.',
                'category' => ListingCategory::Bakery,
                'photo_path' => 'listings/demo/croissants.svg',
                'original_price_sen' => 1200,
                'price_sen' => 600,
                'halal_status' => HalalStatus::Certified,
                'allergens' => ['egg', 'milk', 'wheat'],
            ],
            'puffs' => [
                'shop' => 'maklong',
                'type' => ListingType::Regular,
                'title' => 'Curry puffs × 4',
                'description' => 'Four potato curry puffs from this afternoon’s batch.',
                'category' => ListingCategory::Kuih,
                'photo_path' => 'listings/demo/curry-puffs.svg',
                'original_price_sen' => 600,
                'price_sen' => 300,
                'halal_status' => HalalStatus::MuslimOwned,
                'allergens' => ['wheat'],
            ],
            'latte' => [
                'shop' => 'dailygrind',
                'type' => ListingType::Regular,
                'title' => 'Iced latte',
                'description' => 'Iced latte made to order before closing.',
                'category' => ListingCategory::Drinks,
                'photo_path' => 'listings/demo/iced-latte.svg',
                'original_price_sen' => 900,
                'price_sen' => 450,
                'halal_status' => HalalStatus::MuslimOwned,
                'allergens' => ['milk'],
            ],
            'sandwich' => [
                'shop' => 'dailygrind',
                'type' => ListingType::Regular,
                'title' => 'Sandwich & pastry pack',
                'description' => 'Two sandwiches and two pastries from today’s display.',
                'category' => ListingCategory::Bakery,
                'photo_path' => 'listings/demo/sandwich-pack.svg',
                'original_price_sen' => 2400,
                'price_sen' => 900,
                'halal_status' => HalalStatus::MuslimOwned,
                'allergens' => ['egg', 'milk', 'wheat'],
            ],
            'nasiayam' => [
                'shop' => 'kafeseriilmu',
                'type' => ListingType::Regular,
                'title' => 'Nasi ayam set (2 pax)',
                'description' => 'Chicken rice for two with soup and sambal.',
                'category' => ListingCategory::Meals,
                'photo_path' => 'listings/demo/nasi-ayam.svg',
                'original_price_sen' => 1300,
                'price_sen' => 650,
                'halal_status' => HalalStatus::MuslimOwned,
                'allergens' => ['soy'],
            ],
        ];
    }

    /**
     * Create one listing for a product.
     *
     * @param  array<string, Seller>  $shops
     * @param  array<string, mixed>  $overrides
     */
    private function listing(array $shops, string $product, array $overrides): Listing
    {
        $data = $this->products()[$product];
        $shop = $shops[$data['shop']];
        unset($data['shop']);

        return Listing::factory()->for($shop)->create([...$data, ...$overrides]);
    }

    /**
     * Today's listings: the deals list, the listing page and the seller
     * dashboard. Minutes are counted from the moment of seeding.
     *
     * @param  array<string, Seller>  $shops
     * @return array<string, Listing>
     */
    private function tonightsListings(array $shops): array
    {
        $window = fn (int $start, int $end) => [
            'pickup_start' => $this->now->addMinutes($start),
            'pickup_end' => $this->now->addMinutes($end),
            'published_at' => $this->now->subHours(4),
        ];

        return [
            'puffs' => $this->listing($shops, 'puffs', [
                ...$window(-60, 18), 'quantity_total' => 6, 'quantity_available' => 4,
            ]),
            'latte' => $this->listing($shops, 'latte', [
                ...$window(-45, 33), 'quantity_total' => 8, 'quantity_available' => 5,
            ]),
            // Pickup window opens in 42 minutes and lasts two hours; 3 of 5 left.
            'bundle' => $this->listing($shops, 'bundle', [
                ...$window(42, 162), 'quantity_total' => 5, 'quantity_available' => 3, 'offer_leftovers' => true,
            ]),
            'nasiayam' => $this->listing($shops, 'nasiayam', [
                ...$window(-30, 58), 'quantity_total' => 4, 'quantity_available' => 2,
            ]),
            'sandwich' => $this->listing($shops, 'sandwich', [
                ...$window(-25, 95), 'quantity_total' => 5, 'quantity_available' => 4,
            ]),
            // The flash deal that sold out and ended an hour ago, so it is closed.
            'croissants' => $this->listing($shops, 'croissants', [
                ...$window(-180, -60), 'quantity_total' => 4, 'quantity_available' => 0,
                'status' => ListingStatus::Closed, 'closed_at' => $this->now->subMinutes(60),
            ]),
        ];
    }

    /**
     * Today's reservations, including the "Pickups" table on the seller
     * dashboard mockup with its pickup codes.
     *
     * @param  array<string, Listing>  $listings
     * @param  array<string, User>  $customers
     */
    private function tonightsReservations(array $listings, array $customers): void
    {
        $bundle = Reservation::factory()->for($listings['bundle']);
        $croissants = Reservation::factory()->for($listings['croissants']);

        $bundle->paidNow()->create(['customer_id' => $customers['aina']->id, 'pickup_code' => '4721']);
        $bundle->create(['customer_id' => $customers['hafiz']->id, 'pickup_code' => '3390']);

        $croissants->collected()->create([
            'customer_id' => $customers['priya']->id, 'pickup_code' => '6208',
            'collected_at' => $this->now->subMinutes(150),
        ]);
        $croissants->collected()->create([
            'customer_id' => $customers['meiling']->id, 'pickup_code' => '1185', 'quantity' => 2,
            'collected_at' => $this->now->subMinutes(120),
        ]);
        $croissants->noShow()->create(['customer_id' => $customers['daniel']->id, 'pickup_code' => '9052']);

        // The other shops' listings: every portion that is no longer
        // available belongs to a reservation, so the stock numbers add up.
        Reservation::factory()->for($listings['puffs'])->create([
            'customer_id' => $customers['priya']->id, 'pickup_code' => '2846', 'quantity' => 2,
        ]);
        Reservation::factory()->for($listings['latte'])->paidNow()->create([
            'customer_id' => $customers['jason']->id, 'pickup_code' => '5173', 'quantity' => 2,
        ]);
        Reservation::factory()->for($listings['latte'])->collected()->create([
            'customer_id' => $customers['hafiz']->id, 'pickup_code' => '7730',
            'collected_at' => $this->now->subMinutes(20),
        ]);
        Reservation::factory()->for($listings['nasiayam'])->create([
            'customer_id' => $customers['hafiz']->id, 'pickup_code' => '6412', 'quantity' => 2,
        ]);
        // Mei Ling collected this one and then reported it (see the report in run()).
        Reservation::factory()->for($listings['sandwich'])->collected()->create([
            'customer_id' => $customers['meiling']->id, 'pickup_code' => '8059',
            'collected_at' => $this->now->subMinutes(10),
        ]);
    }

    /**
     * Aina's past pickups, so her impact page matches the mockup:
     * 14 meals rescued, RM118 saved, and 1, 2, 4, 2, 3, 2 meals per week over
     * the last six calendar weeks (oldest first, this week last).
     *
     * @param  array<string, Seller>  $shops
     */
    private function ainasOrderHistory(array $shops, User $aina): void
    {
        // [weeks ago, product, portions]
        $history = [
            [5, 'bundle', 1],
            [4, 'puffs', 1], [4, 'latte', 1],
            [3, 'sandwich', 2], [3, 'croissants', 2],
            [2, 'bundle', 1], [2, 'latte', 1],
            [1, 'bundle', 1], [1, 'puffs', 1], [1, 'nasiayam', 1],
            [0, 'nasiayam', 1], [0, 'bundle', 1],
        ];

        $weekStart = $this->now->startOfWeek();
        $secondsIntoWeek = $weekStart->diffInSeconds($this->now);

        foreach ($history as $index => [$weeksAgo, $product, $portions]) {
            // Past weeks: an evening in that week. This week: some time
            // between Monday 00:00 and now, so it is always in the past.
            $collectedAt = $weeksAgo > 0
                ? $weekStart->subWeeks($weeksAgo)->addDays($index % 5)->setTime(19, 30)
                : $weekStart->addSeconds((int) ($secondsIntoWeek * ($index % 2 === 0 ? 0.3 : 0.6)));

            // The window had to be over before the listing could close.
            $windowEnd = $collectedAt->addMinutes(15)->min($this->now);

            $listing = $this->listing($shops, $product, [
                'pickup_start' => $collectedAt->subMinutes(45),
                'pickup_end' => $windowEnd,
                'quantity_total' => 5,
                'quantity_available' => 5 - $portions,
                'status' => ListingStatus::Closed,
                'published_at' => $collectedAt->subHours(4),
                'closed_at' => $windowEnd,
                'created_at' => $collectedAt->subHours(4),
            ]);

            Reservation::factory()->for($listing)->paidNow()->collected()->create([
                'customer_id' => $aina->id,
                'quantity' => $portions,
                // Fixed codes (1000, 1001, …) so the demo data is the same every time.
                'pickup_code' => (string) (1000 + $index),
                'collected_at' => $collectedAt,
                'created_at' => $collectedAt->subHours(2),
            ]);
        }
    }
}
