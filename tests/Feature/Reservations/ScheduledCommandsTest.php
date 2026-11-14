<?php

use App\Enums\DonationStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
});

/** Everything in the database that the two commands are allowed to change. */
function engineSnapshot(): array
{
    return [
        'reservations' => Reservation::orderBy('id')->get(['id', 'status', 'late_cancel'])->toArray(),
        'listings' => Listing::orderBy('id')->get(['id', 'status', 'quantity_available', 'closed_at'])->toArray(),
        'users' => User::orderBy('id')->get(['id', 'no_show_strikes', 'blocked_until'])->toArray(),
        'donations' => Donation::orderBy('id')->get(['id', 'listing_id', 'quantity', 'status'])->toArray(),
    ];
}

it('runs both commands every minute', function () {
    $events = collect(app(Schedule::class)->events())
        ->mapWithKeys(fn ($event) => [str($event->command)->afterLast(' ')->toString() => $event->expression]);

    expect($events->all())->toBe([
        'reservations:expire' => '* * * * *',
        'listings:close' => '* * * * *',
    ]);
});

describe('reservations:expire', function () {
    it('turns only overdue reservations into no-shows', function () {
        $overdue = Reservation::factory()->for(Listing::factory()->window(-120, -16))->create();
        $inGrace = Reservation::factory()->for(Listing::factory()->window(-120, -10))->create();
        $upcoming = Reservation::factory()->create();
        $collected = Reservation::factory()->for(Listing::factory()->window(-120, -16))->collected()->create();

        $this->artisan('reservations:expire')
            ->expectsOutput('Marked 1 reservation(s) as no-show.')
            ->assertSuccessful();

        expect($overdue->fresh()->status)->toBe(ReservationStatus::NoShow)
            ->and($overdue->customer->fresh()->no_show_strikes)->toBe(1)
            ->and($inGrace->fresh()->status)->toBe(ReservationStatus::Reserved)
            ->and($upcoming->fresh()->status)->toBe(ReservationStatus::Reserved)
            ->and($collected->fresh()->status)->toBe(ReservationStatus::Collected);
    });

    it('changes nothing when it runs a second time', function () {
        Reservation::factory()->count(3)->for(Listing::factory()->window(-120, -16))->create();

        $this->artisan('reservations:expire')->expectsOutput('Marked 3 reservation(s) as no-show.');
        $after = engineSnapshot();

        $this->artisan('reservations:expire')->expectsOutput('Marked 0 reservation(s) as no-show.');

        expect(engineSnapshot())->toBe($after);
    });

    it('blocks a customer whose third no-show comes in one run', function () {
        $customer = User::factory()->create();
        Reservation::factory()->count(3)
            ->for(Listing::factory()->window(-120, -16))
            ->create(['customer_id' => $customer->id]);

        $this->artisan('reservations:expire');

        expect($customer->fresh()->isBlocked())->toBeTrue()
            ->and($customer->fresh()->no_show_strikes)->toBe(0);
    });
});

describe('listings:close', function () {
    it('closes published listings whose window has ended and leaves the rest', function () {
        $ended = Listing::factory()->window(-120, -1)->create();
        $endedPaused = Listing::factory()->window(-120, -1)->paused()->create();
        $endedSoldOut = Listing::factory()->window(-120, -1)->soldOut()->create();
        $endsNow = Listing::factory()->window(-120, 0)->create();
        $endedDraft = Listing::factory()->window(-120, -1)->draft()->create();
        $running = Listing::factory()->window(-60, 60)->create();

        $this->artisan('listings:close')
            ->expectsOutput('Closed 4 listing(s). Offered leftovers from 0 listing(s) to the food bank.')
            ->assertSuccessful();

        expect($ended->fresh()->status)->toBe(ListingStatus::Closed)
            ->and($ended->fresh()->closed_at->equalTo(now()))->toBeTrue()
            ->and($endedPaused->fresh()->status)->toBe(ListingStatus::Closed)
            ->and($endedSoldOut->fresh()->status)->toBe(ListingStatus::Closed)
            ->and($endsNow->fresh()->status)->toBe(ListingStatus::Closed)
            ->and($endedDraft->fresh()->status)->toBe(ListingStatus::Draft)
            ->and($running->fresh()->status)->toBe(ListingStatus::Live);
    });

    it('offers the leftovers of a closed listing to the food bank, once', function () {
        $partner = FoodBankPartner::factory()->create();
        $listing = Listing::factory()->window(-120, -1)->create([
            'quantity_total' => 5, 'quantity_available' => 3, 'offer_leftovers' => true,
        ]);

        $this->artisan('listings:close')
            ->expectsOutput('Closed 1 listing(s). Offered leftovers from 1 listing(s) to the food bank.');

        $donation = Donation::sole();

        expect($donation->listing->is($listing))->toBeTrue()
            ->and($donation->partner->is($partner))->toBeTrue()
            ->and($donation->quantity)->toBe(3)
            ->and($donation->status)->toBe(DonationStatus::Offered);

        $after = engineSnapshot();

        $this->artisan('listings:close')
            ->expectsOutput('Closed 0 listing(s). Offered leftovers from 0 listing(s) to the food bank.');

        expect(engineSnapshot())->toBe($after);
    });

    it('makes no offer when the seller did not tick it, nothing is left, or there is no partner', function () {
        Listing::factory()->window(-120, -1)->create(['offer_leftovers' => false]);
        Listing::factory()->window(-120, -1)->soldOut()->create(['offer_leftovers' => true]);

        $this->artisan('listings:close');
        expect(Donation::count())->toBe(0);

        FoodBankPartner::factory()->create();
        $this->artisan('listings:close');
        expect(Donation::count())->toBe(0);
    });

    it('waits for the grace period before counting leftovers, then includes the no-shows', function () {
        FoodBankPartner::factory()->create();
        $listing = Listing::factory()->window(-120, -1)->create([
            'quantity_total' => 5, 'quantity_available' => 3, 'offer_leftovers' => true,
        ]);
        Reservation::factory()->for($listing)->create(['quantity' => 2]);

        // Window over, but the customer still has grace time: closed, no offer yet.
        $this->artisan('reservations:expire');
        $this->artisan('listings:close');

        expect($listing->fresh()->status)->toBe(ListingStatus::Closed)
            ->and(Donation::count())->toBe(0);

        // Grace over: the 2 uncollected portions join the 3 unsold ones.
        $this->travel(15)->minutes();
        $this->artisan('reservations:expire');
        $this->artisan('listings:close');

        expect(Donation::sole()->quantity)->toBe(5);
    });
});

it('handles a whole evening: collect, free cancel, no-show, close, donate', function () {
    FoodBankPartner::factory()->create();
    $listing = Listing::factory()->window(60, 180)->create([
        'quantity_total' => 5, 'quantity_available' => 5, 'offer_leftovers' => true,
    ]);
    [$aina, $hafiz, $priya] = User::factory()->count(3)->create();

    $ainas = reservations()->reserve($aina, $listing, 2, PaymentMethod::PayNow);
    $hafizs = reservations()->reserve($hafiz, $listing, 1, PaymentMethod::PayAtPickup);
    $priyas = reservations()->reserve($priya, $listing, 2, PaymentMethod::PayAtPickup);
    expect($listing->fresh()->status)->toBe(ListingStatus::SoldOut);

    // Priya changes her mind early: free, and her 2 portions are back on sale.
    reservations()->cancel($priyas);
    expect($listing->fresh()->status)->toBe(ListingStatus::Live)
        ->and($listing->fresh()->quantity_available)->toBe(2);

    // Aina collects during the window. Hafiz never comes.
    $this->travel(90)->minutes();
    reservations()->collect($listing->seller, $ainas->pickup_code);

    // The minute-by-minute commands run all evening.
    foreach (range(1, 120) as $minute) {
        $this->travel(1)->minutes();
        $this->artisan('reservations:expire');
        $this->artisan('listings:close');
    }

    expect($ainas->fresh()->status)->toBe(ReservationStatus::Collected)
        ->and($priyas->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($hafizs->fresh()->status)->toBe(ReservationStatus::NoShow)
        ->and($aina->fresh()->no_show_strikes)->toBe(0)
        ->and($priya->fresh()->no_show_strikes)->toBe(0)
        ->and($hafiz->fresh()->no_show_strikes)->toBe(1)
        ->and($listing->fresh()->status)->toBe(ListingStatus::Closed)
        // 5 made, 2 collected: 2 unsold + Hafiz's 1 go to the food bank.
        ->and(Donation::sole()->quantity)->toBe(3);
});

it('finds nothing to do on freshly seeded demo data', function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->seed(DatabaseSeeder::class);
    $before = engineSnapshot();

    $this->artisan('reservations:expire')->expectsOutput('Marked 0 reservation(s) as no-show.');
    $this->artisan('listings:close')
        ->expectsOutput('Closed 0 listing(s). Offered leftovers from 0 listing(s) to the food bank.');

    expect(engineSnapshot())->toBe($before);
});
