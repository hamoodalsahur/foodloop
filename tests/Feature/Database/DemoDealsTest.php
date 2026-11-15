<?php

// The bigger demo menu (App\Support\Demo\DemoMenu) and the "random" way of
// posting tonight's deals from it (App\Support\Demo\DemoDeals). The other
// seeder tests run with DEMO_MENU=mockup (phpunit.xml): the six mockup deals.

use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Queries\DealsQuery;
use App\Support\DealFilters;
use App\Support\Demo\DemoDeals;
use App\Support\Demo\DemoMenu;
use App\Support\Distance;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    /** Seed a fresh database with the random menu and a fixed pick. */
    $this->seedRandom = function (int $seed) {
        config(['foodloop.demo_menu' => 'random', 'foodloop.demo_menu_seed' => $seed]);
        $this->seed(DatabaseSeeder::class);
    };

    /** Tonight's deals: everything not closed. */
    $this->tonight = fn () => Listing::whereIn('status', [ListingStatus::Live, ListingStatus::SoldOut])->with('seller')->get();
});

it('has a menu of about 25 items across the five demo shops', function () {
    $items = collect(DemoMenu::items());

    expect($items->count())->toBeGreaterThanOrEqual(25)
        ->and($items->pluck('shop')->unique()->sort()->values()->all())->toBe(collect(DemoMenu::SHOPS)->keys()->sort()->values()->all())
        ->and($items->pluck('title')->unique()->count())->toBe($items->count())
        ->and($items->where('type', ListingType::Bundle)->count())->toBeGreaterThanOrEqual(3)
        ->and($items->where('type', ListingType::Regular)->count())->toBeGreaterThanOrEqual(15);

    $items->groupBy('shop')->each(fn ($ofShop, $shop) => expect($ofShop->count())->toBeGreaterThanOrEqual(4, $shop));
});

it('prices every item sensibly: whole 50 sen, 40 to 70% off', function () {
    foreach (DemoMenu::items() as $item) {
        $off = 1 - $item['price_sen'] / $item['original_price_sen'];

        expect($item['price_sen'])->toBeGreaterThanOrEqual(200, $item['title'])
            ->and($item['original_price_sen'])->toBeLessThanOrEqual(3000, $item['title'])
            ->and($item['price_sen'] % 50)->toBe(0, $item['title'])
            ->and($item['original_price_sen'] % 50)->toBe(0, $item['title'])
            ->and($off)->toBeGreaterThanOrEqual(0.4, $item['title'])
            ->and($off)->toBeLessThanOrEqual(0.7, $item['title']);
    }
});

it('gives every item the halal status of its shop and only allergens the app knows', function () {
    foreach (DemoMenu::items() as $item) {
        // Only Bonda Mawar has a halal certificate on file in the demo.
        expect($item['halal_status'])->toBe($item['shop'] === 'bondamawar' ? HalalStatus::Certified : HalalStatus::MuslimOwned, $item['title'])
            ->and(array_diff($item['allergens'], config('foodloop.allergens')))->toBe([], $item['title'])
            ->and(mb_strlen($item['title']))->toBeLessThanOrEqual(30, $item['title']);
    }
});

it('keeps the two deals of the demo script exactly the same in the random menu', function (int $seed) {
    ($this->seedRandom)($seed);

    $bundle = Listing::live()->where('title', 'Bakery Surprise Bundle')->with('seller')->sole();
    $sandwich = Listing::live()->where('title', 'Sandwich & pastry pack')->with('seller')->sole();

    expect($bundle->seller->business_name)->toBe('Bonda Mawar Cake House')
        ->and($bundle->price_formatted)->toBe('RM8')
        ->and($bundle->original_price_formatted)->toBe('RM20')
        ->and($bundle->quantity_total)->toBe(5)
        ->and($bundle->quantity_available)->toBe(3)
        ->and($bundle->isOpen())->toBeTrue()
        ->and($bundle->reservations()->where('status', ReservationStatus::Reserved)->with('customer')->get()
            ->map(fn (Reservation $r) => [$r->pickup_code, $r->customer->email])->all())
        ->toBe([['4721', 'aina@foodloop.test'], ['3390', 'hafiz@foodloop.test']])
        ->and($sandwich->seller->business_name)->toBe('Se.Bunga Kafe UiTM')
        ->and($sandwich->price_formatted)->toBe('RM9')
        ->and($sandwich->pickup_start->isPast())->toBeTrue()
        ->and($sandwich->pickup_end->isFuture())->toBeTrue();

    // The seller can hand Aina's sandwich pack over straight away.
    expect(reservations()->collect($sandwich->seller, '2468')->customer->email)->toBe('aina@foodloop.test');

    // The rest of the mockup data is there too (Bonda Mawar's dashboard, the admin's report).
    expect(Reservation::whereIn('pickup_code', ['6208', '1185', '9052', '8059'])->count())->toBe(4);
})->with([1, 2, 3, 7, 42, 2026]);

it('posts 6 to 9 deals in the random menu, at least one open at every shop, all within 5 km', function (int $seed) {
    ($this->seedRandom)($seed);

    $tonight = ($this->tonight)();
    $open = (new DealsQuery)->inRange(new DealFilters(halalOnly: false, withinKm: 5));

    expect($tonight->count())->toBeGreaterThanOrEqual(6)
        ->and($tonight->count())->toBeLessThanOrEqual(9)
        ->and($tonight->pluck('title')->unique()->count())->toBe($tonight->count())
        // No two deals share a picture (there are enough drawings for nine).
        ->and($tonight->pluck('photo_path')->unique()->count())->toBe($tonight->count())
        ->and($tonight->where('status', ListingStatus::SoldOut)->count())->toBeLessThanOrEqual(1)
        // Every shop has a pin on the map: one deal that can still be reserved.
        ->and($open->pluck('seller_id')->unique()->count())->toBe(5)
        // The "closing within the hour" strip is never empty.
        ->and((new DealsQuery)->closingSoon($open))->toBeGreaterThanOrEqual(3);

    $tonight->each(function (Listing $listing) {
        $item = collect(DemoMenu::items())->firstWhere('title', $listing->title);
        $minutes = (int) $listing->pickup_start->diffInMinutes($listing->pickup_end);

        expect($item)->not->toBeNull($listing->title)
            ->and($listing->seller->user->email)->toBe($item['shop'].'@foodloop.test')
            ->and($listing->price_sen)->toBe($item['price_sen'])
            ->and(Distance::toSeller($listing->seller))->toBeLessThan(5.0)
            ->and($listing->pickup_end->isFuture())->toBeTrue()
            ->and($minutes)->toBeGreaterThanOrEqual(60)
            ->and($minutes)->toBeLessThanOrEqual(config('foodloop.max_window_hours') * 60)
            ->and($listing->pickup_start->minute % 5)->toBe(0)
            ->and($listing->quantity_total)->toBeLessThanOrEqual(10);

        Storage::disk('public')->assertExists($listing->photo_path);
    });
})->with([1, 2, 3, 7, 42, 2026]);

it('keeps stock adding up and pickup codes unique in the random menu', function (int $seed) {
    ($this->seedRandom)($seed);

    Listing::with('reservations')->get()->each(function (Listing $listing) {
        $taken = $listing->reservations->where('status', '!=', ReservationStatus::Cancelled)->sum('quantity');

        expect($listing->quantity_available)->toBe($listing->quantity_total - $taken, $listing->title)
            ->and($listing->status === ListingStatus::SoldOut)->toBe($listing->quantity_available === 0 && $listing->status !== ListingStatus::Closed, $listing->title);

        // Nobody holds more than the app would let them reserve.
        $listing->reservations->groupBy('customer_id')->each(
            fn ($theirs) => expect($theirs->sum('quantity'))->toBeLessThanOrEqual(config('foodloop.max_qty_per_listing'))
        );
    });

    $codes = Reservation::where('status', ReservationStatus::Reserved)->pluck('pickup_code');

    expect($codes->unique()->count())->toBe($codes->count())
        ->and($codes->every(fn (string $code) => preg_match('/^\d{4}$/', $code) === 1))->toBeTrue();
})->with([1, 2, 3, 7, 42, 2026]);

it('picks the same deals for the same number and different deals for different numbers', function () {
    $this->travelTo('2026-11-18 18:18:00');
    ($this->seedRandom)(1);

    $pick = function (int $seed) {
        // Take tonight's deals off again and post a new set, as demo:refresh does.
        Reservation::where('status', ReservationStatus::Reserved)->delete();
        Listing::whereIn('status', [ListingStatus::Live, ListingStatus::SoldOut])->update(['status' => ListingStatus::Closed]);

        return collect((new DemoDeals('random', $seed))->post(earlierToday: false))
            ->map(fn (Listing $listing) => [$listing->title, $listing->quantity_total, $listing->pickup_start->toTimeString()])
            ->sortKeys()
            ->all();
    };

    $picks = collect([1, 2, 3, 4, 5, 6])->map(fn (int $seed) => $pick($seed));

    expect($pick(1))->toBe($picks[0])
        ->and($pick(4))->toBe($picks[3])
        // Six different numbers: (nearly) six different menus, and more than the six mockup items in all.
        ->and($picks->map(fn (array $deals) => json_encode($deals))->unique()->count())->toBeGreaterThanOrEqual(5)
        ->and($picks->flatMap(fn (array $deals) => array_keys($deals))->unique()->count())->toBeGreaterThan(12);
});

it('posts a different pick each time when no number is set', function () {
    config(['foodloop.demo_menu' => 'random', 'foodloop.demo_menu_seed' => null]);
    $this->seed(DatabaseSeeder::class);

    $menus = collect(range(1, 6))->map(function () {
        Reservation::where('status', ReservationStatus::Reserved)->delete();
        Listing::whereIn('status', [ListingStatus::Live, ListingStatus::SoldOut])->update(['status' => ListingStatus::Closed]);

        return collect((new DemoDeals)->post(earlierToday: false))->keys()->sort()->implode(',');
    });

    // Six identical picks in a row from 27 items is as good as impossible.
    expect($menus->unique()->count())->toBeGreaterThan(1);
});

it('says no to a menu it does not know', function () {
    expect(fn () => new DemoDeals('breakfast'))->toThrow(InvalidArgumentException::class, 'DEMO_MENU');
});

it('still posts exactly the six mockup deals when the menu is "mockup"', function () {
    $this->seed(DatabaseSeeder::class);

    expect(config('foodloop.demo_menu'))->toBe('mockup')
        ->and(Listing::where('pickup_end', '>', now()->subHours(2))->pluck('title')->sort()->values()->all())->toBe([
            'Bakery Surprise Bundle', 'Butter croissants × 3', 'Curry puffs × 4', 'Iced latte', 'Nasi ayam set (2 pax)', 'Sandwich & pastry pack',
        ])
        ->and(Seller::count())->toBe(8);
});
