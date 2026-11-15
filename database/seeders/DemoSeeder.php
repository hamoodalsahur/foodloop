<?php

namespace Database\Seeders;

use App\Enums\DonationStatus;
use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Demo\DemoDeals;
use App\Support\Demo\DemoMenu;
use App\Support\Demo\DemoPhotos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * DEMO DATA ONLY. Everything here is made up to match the approved mockups
 * (docs/mockups): the shops, people, prices, coordinates and order history are
 * not real offers or research results.
 *
 * Reset everything with:        php artisan migrate:fresh --seed
 * Only put fresh food on sale:  php artisan demo:refresh   (keeps accounts and history)
 *
 * Pickup times are set relative to the moment you seed, so there are always
 * deals "closing soon" no matter what time of day you run it. Tonight's deals
 * themselves come from App\Support\Demo\DemoDeals (shared with demo:refresh),
 * and what the shops sell from App\Support\Demo\DemoMenu.
 */
class DemoSeeder extends Seeder
{
    /** Numbers the demo shops' made-up SSM numbers (DEMO-0001, DEMO-0002…). */
    private int $shopCount = 0;

    /** Every demo account signs in with this password. */
    public const PASSWORD = 'password';

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

        // Start clean, so a photo that was removed from docs/demo-food/real is not used again.
        DemoPhotos::copy(clean: true);

        FoodBankPartner::create([
            'name' => '[FOOD BANK PARTNER NAME]',
            'contact' => '[FOOD BANK CONTACT]',
        ]);

        $this->user('FoodLoop Admin (demo)', 'admin@foodloop.test')
            ->forceFill(['role' => 'admin'])->save();

        $customers = $this->customers();
        $shops = $this->approvedShops();
        $this->shopsWaitingForApproval();

        // Tonight's deals and their reservations. The same class posts them
        // again for `php artisan demo:refresh`.
        (new DemoDeals)->post();
        $this->ainasOrderHistory($shops, $customers['aina']);

        $this->demoNotifications();
    }

    /**
     * Fill the inboxes (the bell) with the messages the demo data above would
     * have caused, with the app's real wording (App\Services\Notifier): every
     * reservation still waiting, every shop waiting for approval, every open
     * report and every leftover offer. Each is dated like the thing it is about.
     */
    private function demoNotifications(): void
    {
        $notifier = app(Notifier::class);

        // Write each message as if it were sent at that moment.
        $at = DemoDeals::at(...);

        Reservation::query()->where('status', ReservationStatus::Reserved)->orderBy('created_at')->get()
            ->each(fn (Reservation $reservation) => $at($reservation->created_at, function () use ($notifier, $reservation) {
                $notifier->reservationBooked($reservation);
                $notifier->newReservation($reservation);
            }));

        Seller::query()->where('status', SellerStatus::Pending)->orderBy('created_at')->get()
            ->each(fn (Seller $shop) => $at($shop->created_at, fn () => $notifier->shopWaiting($shop)));

        Report::query()->where('status', ReportStatus::Open)->orderBy('created_at')->get()
            ->each(fn (Report $report) => $at($report->created_at, fn () => $notifier->listingReported($report)));

        Donation::query()->where('status', DonationStatus::Offered)->orderBy('created_at')->get()
            ->each(fn (Donation $donation) => $at($donation->created_at, fn () => $notifier->leftoversOffered($donation)));
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
     * The approved shops: real food places on and around the UiTM Kedah campus
     * in Merbok, at their real positions (from Google Maps, Nov 2026).
     * DEMO DATA: the names and places are real, but these shops have not
     * joined FoodLoop; their listings, prices and halal statuses are made up.
     *
     * @return array<string, Seller>
     */
    private function approvedShops(): array
    {
        Storage::disk('local')->put(
            'halal-certs/demo-bonda-mawar.txt',
            "DEMO DATA. Placeholder for an uploaded halal certificate. Not a real document.\n"
        );

        return [
            // Cake shop in Kampung Sungai Layar Hujong, 4.5 km south.
            'bondamawar' => $this->shop('Bonda Mawar Cake House', 'bondamawar@foodloop.test', at: [5.6754781, 100.4627562], extra: [
                'address' => 'Kampung Sungai Layar Hujong, Sungai Petani',
                'halal_claim' => HalalStatus::Certified,
                'halal_cert_path' => 'halal-certs/demo-bonda-mawar.txt',
            ])->approved()->create(),
            // Grilled chicken rice right in front of the main gate.
            'merbokroasted' => $this->shop('Merbok Roasted', 'merbokroasted@foodloop.test', at: [5.7141234, 100.4514946], extra: [
                'address' => 'In front of UiTM, Semeling, Bedong',
            ])->approved()->create(),
            // Coffee shop in Taman Lembah Bujang, 1.4 km west.
            'kopisaigon' => $this->shop('Kopi Saigon Merbok', 'kopisaigon@foodloop.test', at: [5.7063555, 100.4394582], extra: [
                'address' => 'Jalan Bujang Indah, Taman Lembah Bujang, Bedong',
            ])->approved()->create(),
            // Traditional kuih maker in Kampung Tupah Lama, 2.6 km west.
            'kuihtupah' => $this->shop('Kuih Tradisional Tupah', 'kuihtupah@foodloop.test', at: [5.72015, 100.4273272], extra: [
                'address' => 'Jalan Kampung Tupah Lama, Merbok',
            ])->approved()->create(),
            // Café on campus, near the Sultan Badlishah library.
            'sebunga' => $this->shop('Se.Bunga Kafe UiTM', 'sebunga@foodloop.test', at: [5.7123608, 100.4483042], extra: [
                'address' => 'UiTM Kedah campus, Merbok',
            ])->approved()->create(),
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

        // Made-up shops (not real places): a real shop shouldn't appear to have applied, or to have sent no SSM number.
        $this->shop('Kedai Kopi Sinar', 'kedaikopisinar@foodloop.test', 0.8, 45, extra: [
            'halal_claim' => HalalStatus::Certified,
            'halal_cert_path' => 'halal-certs/demo-kedai-kopi-sinar.txt',
            'created_at' => $this->now->subHours(2),
        ])->create();

        $this->shop('Dapur Bonda', 'dapurbonda@foodloop.test', 1.2, 135, extra: [
            'created_at' => $this->now->subDay(),
        ])->create();

        // Says "certified" but uploaded nothing, and has no SSM number.
        $this->shop('Sweet Crumbs Bakehouse', 'sweetcrumbs@foodloop.test', 2.1, 225, extra: [
            'ssm_no' => null,
            'halal_claim' => HalalStatus::Certified,
            'halal_cert_path' => null,
            'created_at' => $this->now->subDays(2),
        ])->create();
    }

    /**
     * A shop with its seller account. Placed at the real position `at`
     * ([lat, lng]), or $km away from "you are here" (config
     * foodloop.demo_origin) in the compass direction $bearing (0 = north,
     * 90 = east) for the made-up shops.
     *
     * @param  array{0: float, 1: float}|null  $at
     * @param  array<string, mixed>  $extra
     */
    private function shop(string $name, string $email, float $km = 0, int $bearing = 0, ?array $at = null, array $extra = [])
    {
        $owner = $this->user($name.' (demo seller)', $email);
        $owner->forceFill(['role' => 'seller'])->save();

        [$lat, $lng] = $at ?? [
            config('foodloop.demo_origin.lat') + cos(deg2rad($bearing)) * $km * self::KM,
            config('foodloop.demo_origin.lng') + sin(deg2rad($bearing)) * $km * self::KM,
        ];

        return Seller::factory()->state([
            'user_id' => $owner->id,
            'business_name' => $name,
            // Obviously made up, so nobody mistakes them for the shop's real details.
            'ssm_no' => sprintf('DEMO-%04d', ++$this->shopCount),
            'phone' => 'Demo number',
            'address' => 'Demo address near campus',
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'halal_claim' => HalalStatus::MuslimOwned,
            ...$extra,
        ]);
    }

    /**
     * One closed listing from the past for a catalogue item (App\Support\Demo\DemoMenu).
     *
     * @param  array<string, Seller>  $shops
     * @param  array<string, mixed>  $overrides
     */
    private function listing(array $shops, string $product, array $overrides): Listing
    {
        return Listing::factory()->for($shops[DemoMenu::shopOf($product)])->create([
            ...DemoMenu::listingData($product),
            ...$overrides,
        ]);
    }

    /** Leftovers of a closed listing that went to the placeholder food bank partner. */
    private function donate(Listing $listing, int $quantity, DonationStatus $status, CarbonImmutable $when): void
    {
        Donation::factory()->for($listing)->create([
            'food_bank_partner_id' => FoodBankPartner::query()->value('id'),
            'quantity' => $quantity,
            'status' => $status,
            'created_at' => $when,
            'updated_at' => $when,
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

            // Two of the older bakery bundles had leftovers that the food bank collected.
            if ($product === 'bundle' && in_array($weeksAgo, [1, 2], true)) {
                $this->donate($listing, 5 - $portions, DonationStatus::Collected, $windowEnd);
            }

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
