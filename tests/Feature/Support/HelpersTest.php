<?php

// The small helpers that write distances, pickup windows and countdowns.

use App\Models\Seller;
use App\Support\DealFilters;
use App\Support\Distance;
use App\Support\PickupWindow;
use App\Support\TimeLeft;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

describe('Distance', function () {
    it('measures from the demo "you are here" point in config/foodloop.php', function () {
        $origin = config('foodloop.demo_origin');
        $shop = Seller::factory()->make(['lat' => $origin['lat'] + 0.009, 'lng' => $origin['lng']]);

        expect(Distance::toSeller($shop))->toEqualWithDelta(1.0, 0.01);
    });

    it('has no distance for a shop without coordinates', function () {
        expect(Distance::toSeller(Seller::factory()->make(['lat' => null, 'lng' => null])))->toBeNull()
            ->and(Distance::label(null))->toBeNull();
    });

    it('writes distances the way the deals page shows them', function (float $km, string $label) {
        expect(Distance::label($km))->toBe($label);
    })->with([[0.4, '0.4 km'], [0.04, '0.0 km'], [2.049, '2.0 km'], [9.94, '9.9 km'], [9.96, '10 km'], [12.4, '12 km']]);
});

describe('PickupWindow', function () {
    beforeEach(fn () => $this->travelTo('2026-11-16 18:18:00'));

    it('writes a window as short clock times', function (string $start, string $end, string $label) {
        expect(PickupWindow::times(Carbon::parse($start), Carbon::parse($end)))->toBe($label);
    })->with([
        ['2026-11-16 19:00', '2026-11-16 21:00', '7–9 PM'],
        ['2026-11-16 11:00', '2026-11-16 13:00', '11 AM–1 PM'],
        ['2026-11-16 19:30', '2026-11-16 21:00', '7:30–9 PM'],
        ['2026-11-16 16:55', '2026-11-16 18:55', '4:55–6:55 PM'],
        ['2026-11-16 23:00', '2026-11-17 01:00', '11 PM–1 AM'],
    ]);

    it('names the day in plain words', function (string $moment, string $day) {
        expect(PickupWindow::day(Carbon::parse($moment)))->toBe($day);
    })->with([
        ['2026-11-16 19:00', 'Today'],
        ['2026-11-17 08:00', 'Tomorrow'],
        ['2026-11-15 19:00', 'Yesterday'],
        ['2026-11-20 19:00', 'Fri 20 Nov'],
    ]);

    it('puts the day and the times together', function () {
        expect(PickupWindow::withDay(Carbon::parse('2026-11-16 19:00'), Carbon::parse('2026-11-16 21:00')))
            ->toBe('Today 7–9 PM');
    });
});

it('writes a countdown clock', function (int $seconds, string $clock) {
    $this->travelTo(now()->startOfSecond());

    expect(TimeLeft::clock(now()->addSeconds($seconds)))->toBe($clock);
})->with([[2530, '00:42:10'], [9730, '02:42:10'], [59, '00:00:59'], [0, '00:00:00'], [-30, '00:00:00']]);

describe('DealFilters', function () {
    it('starts with closing soon, halal only, within 2 km', function () {
        $filters = DealFilters::fromRequest(Request::create('/'));

        expect($filters->sort)->toBe('closing')
            ->and($filters->halalOnly)->toBeTrue()
            ->and($filters->withinKm)->toBe(2)
            ->and($filters->category)->toBeNull()
            ->and($filters->type)->toBeNull();
    });

    it('falls back to the defaults for anything it does not recognise', function () {
        $filters = DealFilters::fromRequest(Request::create('/?sort=price&category=pizza&type=x&within=900&halal=maybe'));

        expect($filters->sort)->toBe('closing')
            ->and($filters->category)->toBeNull()
            ->and($filters->type)->toBeNull()
            ->and($filters->withinKm)->toBeNull()
            ->and($filters->halalOnly)->toBeTrue();
    });

    it('builds short links that keep the other choices', function () {
        $filters = DealFilters::fromRequest(Request::create('/?sort=cheapest&category=bakery'));

        expect($filters->url())->toBe(route('deals.index', ['sort' => 'cheapest', 'category' => 'bakery']))
            ->and($filters->url(['halal' => '0']))->toContain('sort=cheapest', 'category=bakery', 'halal=0')
            ->and($filters->url(['category' => null, 'sort' => 'closing']))->toBe(route('deals.index'));
    });

    it('steps the distance 2 km → 5 km → any → 2 km', function () {
        $at = fn (string $query) => DealFilters::fromRequest(Request::create('/'.$query));

        expect($at('')->nextDistance())->toBe(5)
            ->and($at('?within=5')->nextDistance())->toBe('any')
            ->and($at('?within=any')->nextDistance())->toBe(2)
            ->and($at('')->widerDistance())->toBe(5)
            ->and($at('?within=5')->widerDistance())->toBe('any')
            ->and($at('?within=any')->widerDistance())->toBeNull()
            ->and($at('?within=any')->distanceLabel())->toBe('Any distance')
            ->and($at('?within=5')->distanceLabel())->toBe('Within 5 km');
    });
});
