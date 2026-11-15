<?php

namespace App\Support\Demo;

use App\Enums\DonationStatus;
use App\Enums\ListingStatus;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Enums\ReservationStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Services\Notifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * DEMO DATA ONLY. Posts "tonight's deals" for the five demo shops, with the
 * reservations that go with them. Used by BOTH `php artisan migrate:fresh
 * --seed` (Database\Seeders\DemoSeeder) and `php artisan demo:refresh`
 * (App\Support\Demo\DemoRefresh), so the two give the same kind of data.
 *
 * Two menus (config foodloop.demo_menu, DEMO_MENU in .env):
 *
 *   random  two fixed deals plus a different pick from the catalogue
 *           (DemoMenu) each time: 6 to 9 deals in all, with random portions
 *           and pickup times. The default.
 *   mockup  exactly the six deals on the approved mockups, the same every
 *           time. The tests use this one.
 *
 * ALWAYS THE SAME, in both menus, because docs/DEMO_SCRIPT.md is written
 * around them:
 *   - Bakery Surprise Bundle at Bonda Mawar, RM8 instead of RM20, 3 of 5
 *     left, with Aina's reservation (code 4721) and Hafiz's (3390).
 *   - Sandwich & pastry pack at Se.Bunga, pickup window already open, with
 *     Aina's reservation (code 2468) for the seller to hand over.
 *
 * Every time is counted from the moment this runs.
 */
class DemoDeals
{
    /** Customers the deals need, by the part of their login before the @. */
    public const CUSTOMERS = ['aina', 'hafiz', 'priya', 'meiling', 'daniel', 'jason'];

    /** Pickup codes with a fixed meaning in the demo; random codes never use them. */
    private const FIXED_CODES = ['4721', '3390', '6208', '1185', '9052', '2846', '5173', '7730', '6412', '8059', '2468'];

    private Randomizer $random;

    private string $menu;

    private CarbonImmutable $now;

    /** The last 5-minute mark, so pickup times read like real ones ("4:55 PM", not "4:53 PM"). */
    private CarbonImmutable $slot;

    /** @var array<string, Seller> */
    private array $shops;

    /** @var array<string, User> */
    private array $customers;

    /** @var list<string> */
    private array $usedCodes = self::FIXED_CODES;

    /** @var list<Reservation> the reservations made by post() that are still waiting */
    private array $waiting = [];

    /** Made by post(), when it made one. */
    private ?Report $newReport = null;

    private ?Donation $newDonation = null;

    /**
     * @param  string|null  $menu  "random" or "mockup"; null = config foodloop.demo_menu
     * @param  int|null  $seed  the same number gives the same random pick; null = config
     *                          foodloop.demo_menu_seed, and when that is empty a new pick every time
     */
    public function __construct(?string $menu = null, ?int $seed = null)
    {
        $this->menu = $menu ?? config('foodloop.demo_menu', 'random');

        if (! in_array($this->menu, ['random', 'mockup'], true)) {
            throw new InvalidArgumentException("The demo menu is \"random\" or \"mockup\", not \"{$this->menu}\". Check DEMO_MENU in .env.");
        }

        $seed ??= config('foodloop.demo_menu_seed') === null ? null : (int) config('foodloop.demo_menu_seed');

        $this->random = new Randomizer(new Mt19937($seed ?? random_int(0, PHP_INT_MAX)));
    }

    /**
     * What has to be in the database before deals can be posted, and isn't:
     * [] when everything is there. Sentences the owner can read.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        $missing = [];

        if (! User::where('email', 'admin@foodloop.test')->exists()) {
            $missing[] = 'the demo admin (admin@foodloop.test)';
        }

        foreach (DemoMenu::SHOPS as $login => $name) {
            $shop = User::firstWhere('email', "{$login}@foodloop.test")?->seller;

            if ($shop === null) {
                $missing[] = "the demo shop {$name} ({$login}@foodloop.test)";
            } elseif (! $shop->isApproved()) {
                $missing[] = "{$name} as an approved shop (it is {$shop->status->value} now)";
            }
        }

        if (($bonda = User::firstWhere('email', 'bondamawar@foodloop.test')?->seller) && $bonda->isApproved()
            && ! $bonda->hasApprovedHalalCertificate()) {
            $missing[] = 'the approved halal certificate of Bonda Mawar Cake House';
        }

        foreach (self::CUSTOMERS as $login) {
            if (! User::where('email', "{$login}@foodloop.test")->exists()) {
                $missing[] = "the demo customer {$login}@foodloop.test";
            }
        }

        return $missing;
    }

    /**
     * Post the deals and their reservations.
     *
     * $earlierToday also adds what "already happened today" on the mockups:
     * the croissant flash deal at Bonda Mawar that sold out and closed an
     * hour ago (two pickups, one no-show, one leftover offered to the food
     * bank) and Mei Ling's collected sandwich pack. The seeder always wants
     * these; demo:refresh only when today's are not there already, so
     * refreshing ten times doesn't add ten of them to the history.
     *
     * @return array<string, Listing> the new listings, by their DemoMenu key
     */
    public function post(bool $earlierToday = true): array
    {
        $this->now = CarbonImmutable::now()->startOfMinute();
        $this->slot = $this->now->subMinutes($this->now->minute % 5);
        $this->waiting = [];
        $this->newReport = $this->newDonation = null;

        $this->shops = collect(DemoMenu::SHOPS)
            ->map(fn (string $name, string $login) => User::where('email', "{$login}@foodloop.test")->sole()->seller)
            ->all();
        $this->customers = collect(self::CUSTOMERS)
            ->mapWithKeys(fn (string $login) => [$login => User::where('email', "{$login}@foodloop.test")->sole()])
            ->all();

        $listings = $this->menu === 'mockup' ? $this->mockupDeals() : $this->randomDeals();
        $listings += $this->anchors($earlierToday);

        if ($earlierToday) {
            $listings['croissants-earlier'] = $this->earlierFlashDeal();
        }

        $this->staleBreadReport($listings['sandwich']);

        return $listings;
    }

    /**
     * Put the messages the new deals would have caused in the inboxes (the
     * bell), with the app's real wording: "You're booked" for each customer
     * still waiting and "New reservation" for the shop, dated like the
     * reservation, plus the report and the leftover offer when post() made
     * them. Call after post(). (The seeder doesn't: it writes the messages
     * for everything it created in one go, see DemoSeeder::demoNotifications.)
     */
    public function announce(): void
    {
        $notifier = app(Notifier::class);

        foreach ($this->waiting as $reservation) {
            self::at($reservation->created_at, function () use ($notifier, $reservation) {
                $notifier->reservationBooked($reservation);
                $notifier->newReservation($reservation);
            });
        }

        if ($this->newReport !== null) {
            $notifier->listingReported($this->newReport);
        }

        if ($this->newDonation !== null) {
            self::at($this->newDonation->created_at, fn () => $notifier->leftoversOffered($this->newDonation));
        }
    }

    /** Do $send as if it were $moment (so a message gets that date), then put the clock back. */
    public static function at(CarbonInterface $moment, Closure $send): void
    {
        $clock = Carbon::getTestNow();
        Carbon::setTestNow($moment);

        try {
            $send();
        } finally {
            Carbon::setTestNow($clock);
        }
    }

    /**
     * The two deals the demo script is written around. Never change these
     * without changing docs/DEMO_SCRIPT.md.
     *
     * @return array<string, Listing>
     */
    private function anchors(bool $earlierToday): array
    {
        // Reservations close in about 45 minutes; the window lasts two hours; 3 of 5 left.
        $bundle = $this->listing('bundle', 45, 165, total: 5, available: 3, extra: ['offer_leftovers' => true]);

        $this->reserve($bundle, 'aina', '4721', paid: true);
        $this->reserve($bundle, 'hafiz', '3390');

        // Pickup is open right now, so a seller can hand this one over in a live demo.
        // Mei Ling's portion is one of the five; without her there were four.
        $sandwich = $this->listing('sandwich', -25, 95, total: $earlierToday ? 5 : 4, available: 3);

        // FOR THE PITCH DEMO: Aina is "at the counter" of Se.Bunga with code
        // 2468. The seller types it and marks it collected (docs/DEMO_SCRIPT.md).
        $this->reserve($sandwich, 'aina', '2468');

        if ($earlierToday) {
            // Mei Ling collected hers ten minutes ago, and then reported it (staleBreadReport()).
            Reservation::factory()->for($sandwich)->collected()->create([
                'customer_id' => $this->customers['meiling']->id, 'pickup_code' => '8059',
                'collected_at' => $this->now->subMinutes(10),
            ]);
        }

        return ['bundle' => $bundle, 'sandwich' => $sandwich];
    }

    /**
     * The other deals on the mockups, with the reservations that make their
     * "left" numbers add up.
     *
     * @return array<string, Listing>
     */
    private function mockupDeals(): array
    {
        $puffs = $this->listing('puffs', 20, 80, total: 6, available: 4);
        $latte = $this->listing('latte', 35, 95, total: 8, available: 5);
        $nasiayam = $this->listing('nasiayam', 60, 120, total: 4, available: 2);

        $this->reserve($puffs, 'priya', '2846', quantity: 2);
        $this->reserve($latte, 'jason', '5173', quantity: 2, paid: true);
        $this->reserve($latte, 'hafiz', '7730');
        $this->reserve($nasiayam, 'hafiz', '6412', quantity: 2);

        return ['puffs' => $puffs, 'latte' => $latte, 'nasiayam' => $nasiayam];
    }

    /**
     * A different pick each time: one deal from each of the four shops other
     * than Bonda Mawar (whose bundle is always there), so every shop has a pin
     * on the map, plus up to three more from anywhere. With the two anchors
     * that is 6 to 9 deals. Two of them stop taking reservations within the hour, and
     * sometimes one is already sold out.
     *
     * @return array<string, Listing>
     */
    private function randomDeals(): array
    {
        $choices = collect(DemoMenu::items())->except(DemoMenu::ANCHORS);

        // Two deals with the same picture look like a mistake, so an item whose
        // picture is already on tonight's menu is only taken when nothing else is left.
        $pictures = collect(DemoMenu::items())->map(fn (array $item) => DemoPhotos::path($item['photo'], $item['picture']));
        $shown = $pictures->only(DemoMenu::ANCHORS)->values()->all();

        $fresh = function (array $keys) use ($pictures, &$shown): array {
            $new = array_values(array_filter($keys, fn (string $key) => ! in_array($pictures[$key], $shown, true)));

            return $new !== [] ? $new : $keys;
        };

        // One from each shop first…
        $picked = [];
        foreach (array_diff(array_keys(DemoMenu::SHOPS), ['bondamawar']) as $shop) {
            $picked[] = $key = $this->pick($fresh($choices->where('shop', $shop)->keys()->all()));
            $shown[] = $pictures[$key];
        }

        // …then 0 to 3 more from whatever is left.
        $more = [];
        $rest = $choices->except($picked)->keys()->all();

        for ($extra = $this->random->getInt(0, 3); $extra > 0 && $rest !== []; $extra--) {
            $more[] = $key = $this->pick($fresh($rest));
            $shown[] = $pictures[$key];
            $rest = array_values(array_diff($rest, [$key]));
        }

        // Two in three times, one of the extra ones is sold out already.
        $soldOut = $more !== [] && $this->random->getInt(1, 3) <= 2 ? $this->pick($more) : null;

        $onSale = array_values(array_diff([...$picked, ...$more], [$soldOut]));
        $closingSoon = array_slice($this->random->shuffleArray($onSale), 0, 2);

        $listings = [];

        foreach ([...$picked, ...$more] as $key) {
            // Minutes from now until the pickup window opens (= reservations close).
            $start = in_array($key, $closingSoon, true)
                ? $this->random->getInt(3, 10) * 5      // 15 to 50 minutes: "closing soon"
                : $this->random->getInt(13, 36) * 5;    // 65 minutes to 3 hours
            $length = $this->pick([60, 90, 120]);

            if ($key === $soldOut) {
                $total = $this->random->getInt(2, 4);
                $taken = $total;
            } else {
                $total = $this->random->getInt(3, 10);
                $taken = $this->random->getInt(0, min(3, $total - 1));
            }

            $listings[$key] = $this->listing($key, $start, $start + $length, $total, $total - $taken, [
                'status' => $taken === $total ? ListingStatus::SoldOut : ListingStatus::Live,
                'published_at' => $this->slot->subMinutes($this->random->getInt(4, 20) * 15),
                'offer_leftovers' => $this->random->getInt(1, 3) === 1,
            ]);

            // Every portion that is gone belongs to a reservation, so the stock adds up.
            $customers = $this->random->shuffleArray(['hafiz', 'priya', 'meiling', 'jason']);

            while ($taken > 0) {
                $quantity = min($taken, $this->random->getInt(1, 2));
                $taken -= $quantity;

                $this->reserve(
                    $listings[$key],
                    array_shift($customers),
                    $this->newCode(),
                    $quantity,
                    paid: $this->random->getInt(0, 1) === 1,
                    // Some time between when the deal was posted and five minutes ago.
                    madeAt: $this->now->subMinutes($this->random->getInt(5, 55)),
                );
            }
        }

        return $listings;
    }

    /**
     * The croissant flash deal at Bonda Mawar that sold out and ended an hour
     * ago: the collected and no-show rows of the "Pickups" table on the
     * seller mockup, and the leftover that was offered to the food bank.
     */
    private function earlierFlashDeal(): Listing
    {
        $croissants = $this->listing('croissants', -180, -60, total: 4, available: 0, extra: [
            'status' => ListingStatus::Closed, 'closed_at' => $this->slot->subMinutes(60),
        ]);

        $reservation = Reservation::factory()->for($croissants);

        $reservation->collected()->create([
            'customer_id' => $this->customers['priya']->id, 'pickup_code' => '6208',
            'collected_at' => $this->now->subMinutes(150),
        ]);
        $reservation->collected()->create([
            'customer_id' => $this->customers['meiling']->id, 'pickup_code' => '1185', 'quantity' => 2,
            'collected_at' => $this->now->subMinutes(120),
        ]);
        $reservation->noShow()->create(['customer_id' => $this->customers['daniel']->id, 'pickup_code' => '9052']);

        // Daniel's uncollected croissants were offered to the food bank when the listing closed.
        if (($partner = FoodBankPartner::query()->orderBy('id')->value('id')) !== null) {
            $when = $this->now->subMinutes(40);

            $donation = Donation::factory()->for($croissants)->create([
                'food_bank_partner_id' => $partner,
                'quantity' => 1,
                'status' => DonationStatus::Offered,
                'created_at' => $when,
                'updated_at' => $when,
            ]);

            $this->newDonation = $donation;
        }

        return $croissants;
    }

    /**
     * Mei Ling's report about the sandwich pack ("Reported listings" on the
     * admin page). Only one is ever open, so refreshing doesn't pile them up.
     */
    private function staleBreadReport(Listing $sandwich): void
    {
        $meiling = $this->customers['meiling'];

        if (Report::where('reporter_id', $meiling->id)->where('status', ReportStatus::Open)->exists()) {
            return;
        }

        $this->newReport = Report::factory()->create([
            'reporter_id' => $meiling->id,
            'listing_id' => $sandwich->id,
            'reason' => ReportReason::FoodQuality,
            'details' => 'Bread was stale at pickup.',
        ]);
    }

    /**
     * One listing for a catalogue item. $start and $end are minutes from the
     * last 5-minute mark (negative = already passed).
     *
     * @param  array<string, mixed>  $extra
     */
    private function listing(string $key, int $start, int $end, int $total, int $available, array $extra = []): Listing
    {
        return Listing::factory()->for($this->shops[DemoMenu::shopOf($key)])->create([
            ...DemoMenu::listingData($key),
            'pickup_start' => $this->slot->addMinutes($start),
            'pickup_end' => $this->slot->addMinutes($end),
            'published_at' => $this->slot->subHours(4),
            'quantity_total' => $total,
            'quantity_available' => $available,
            ...$extra,
        ]);
    }

    /** A reservation that is still waiting to be collected. */
    private function reserve(Listing $listing, string $customer, string $code, int $quantity = 1, bool $paid = false, ?CarbonInterface $madeAt = null): void
    {
        $factory = Reservation::factory()->for($listing);

        $this->waiting[] = ($paid ? $factory->paidNow() : $factory)->create([
            'customer_id' => $this->customers[$customer]->id,
            'pickup_code' => $code,
            'quantity' => $quantity,
            'status' => ReservationStatus::Reserved,
            ...($madeAt ? ['created_at' => $madeAt, 'updated_at' => $madeAt] : []),
        ]);
    }

    /** A 4-digit pickup code nobody else has in this set of deals. */
    private function newCode(): string
    {
        do {
            $code = (string) $this->random->getInt(1100, 9999);
        } while (in_array($code, $this->usedCodes, true));

        return $this->usedCodes[] = $code;
    }

    /**
     * @template T
     *
     * @param  list<T>  $choices
     * @return T
     */
    private function pick(array $choices): mixed
    {
        return $choices[$this->random->getInt(0, count($choices) - 1)];
    }
}
