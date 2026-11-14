<?php

// The four rules the owner decided on 14 Nov after Stage 2
// (see docs/BUILD_PLAN.md §3 and docs/PROJECT_NOTES.md).

use App\Enums\ListingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
    $this->customer = User::factory()->create();
});

describe('1. changed my mind: cancelling within 5 minutes of reserving is always free', function () {
    beforeEach(function () {
        // The window opens in 10 minutes, so the normal free-cancel cutoff has already passed.
        $this->listing = Listing::factory()->window(10, 120)->create();
        $this->reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);
    });

    it('is free up to and including the fifth minute', function (int $minutes) {
        $this->travel($minutes)->minutes();

        expect(reservations()->cancel($this->reservation)->late_cancel)->toBeFalse()
            ->and($this->customer->fresh()->no_show_strikes)->toBe(0);
    })->with([0, 4, 5]);

    it('is a late cancel with a strike one second after that', function () {
        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        expect(reservations()->cancel($this->reservation)->late_cancel)->toBeTrue()
            ->and($this->customer->fresh()->no_show_strikes)->toBe(1);
    });

    it('also works when reserving just before the pickup window opens', function () {
        $listing = Listing::factory()->window(2, 60)->create();
        $reservation = reservations()->reserve($this->customer, $listing, 1, PaymentMethod::PayNow);

        $this->travel(3)->minutes();

        expect(reservations()->cancel($reservation)->late_cancel)->toBeFalse();
    });

    it('reads the allowance from config/foodloop.php', function () {
        config(['foodloop.change_of_mind_minutes' => 0]);
        $this->travel(1)->minutes();

        expect(reservations()->cancel($this->reservation)->late_cancel)->toBeTrue();
    });
});

describe('2. max 3 per customer per listing', function () {
    beforeEach(function () {
        $this->listing = Listing::factory()->create(['quantity_total' => 10, 'quantity_available' => 10]);
    });

    it('refuses a second reservation that would take the customer over 3', function () {
        reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayNow);

        expectRefusal(
            fn () => reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayNow),
            RefusalReason::OverListingLimit,
        );

        expect($this->listing->fresh()->quantity_available)->toBe(8);
    });

    it('allows several reservations that add up to exactly 3', function () {
        reservations()->reserve($this->customer, $this->listing, 2, PaymentMethod::PayNow);
        reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);

        expect($this->listing->fresh()->quantity_available)->toBe(7);

        expectRefusal(
            fn () => reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow),
            RefusalReason::OverListingLimit,
        );
    });

    it('still counts portions that were collected or not collected', function (string $state) {
        Reservation::factory()->for($this->listing)->{$state}()->create(['customer_id' => $this->customer->id, 'quantity' => 3]);

        expectRefusal(
            fn () => reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow),
            RefusalReason::OverListingLimit,
        );
    })->with(['collected', 'noShow']);

    it('does not count cancelled reservations', function () {
        $first = reservations()->reserve($this->customer, $this->listing, 3, PaymentMethod::PayNow);
        reservations()->cancel($first);

        expect(reservations()->reserve($this->customer, $this->listing, 3, PaymentMethod::PayNow)->quantity)->toBe(3);
    });

    it('counts each customer and each listing separately', function () {
        reservations()->reserve($this->customer, $this->listing, 3, PaymentMethod::PayNow);

        $someoneElse = reservations()->reserve(User::factory()->create(), $this->listing, 3, PaymentMethod::PayNow);
        $anotherListing = reservations()->reserve($this->customer, Listing::factory()->create(), 3, PaymentMethod::PayNow);

        expect($someoneElse->status)->toBe(ReservationStatus::Reserved)
            ->and($anotherListing->status)->toBe(ReservationStatus::Reserved);
    });
});

describe('3. a cancelled pay-now reservation is refunded (simulated)', function () {
    beforeEach(function () {
        $this->listing = Listing::factory()->window(120, 240)->create();
    });

    it('refunds a free cancel', function () {
        $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);

        expect(reservations()->cancel($reservation)->payment_status)->toBe(PaymentStatus::Refunded)
            ->and($reservation->fresh()->payment_status->label())->toBe('Refunded (simulated)');
    });

    it('refunds a late cancel too (the strike is the penalty)', function () {
        $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);
        $this->travel(110)->minutes();

        $cancelled = reservations()->cancel($reservation);

        expect($cancelled->late_cancel)->toBeTrue()
            ->and($cancelled->payment_status)->toBe(PaymentStatus::Refunded);
    });

    it('leaves a pay-at-pickup reservation unpaid, since nothing was paid', function () {
        $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayAtPickup);

        expect(reservations()->cancel($reservation)->payment_status)->toBe(PaymentStatus::Unpaid);
    });

    it('does not refund a no-show', function () {
        $reservation = reservations()->reserve($this->customer, $this->listing, 1, PaymentMethod::PayNow);
        $this->travelTo($reservation->expires_at->addMinute());

        expect(reservations()->markNoShow($reservation)->payment_status)->toBe(PaymentStatus::SimulatedPaid);
    });
});

describe('4. a blocked customer does not keep their waiting reservations', function () {
    beforeEach(function () {
        $this->customer->forceFill(['no_show_strikes' => 2])->save();

        // Tonight's reservation, which will become the third strike…
        $this->tonight = Listing::factory()->window(60, 120)->create();
        $this->missed = reservations()->reserve($this->customer, $this->tonight, 1, PaymentMethod::PayNow);

        // …and two more the customer made for tomorrow. The first one sold the listing out.
        $this->tomorrowA = Listing::factory()->window(60 * 24, 60 * 26)->create(['quantity_total' => 2, 'quantity_available' => 2]);
        $this->tomorrowB = Listing::factory()->window(60 * 24, 60 * 26)->create();
        $this->paid = reservations()->reserve($this->customer, $this->tomorrowA, 2, PaymentMethod::PayNow);
        $this->unpaid = reservations()->reserve($this->customer, $this->tomorrowB, 1, PaymentMethod::PayAtPickup);

        $this->someoneElses = reservations()->reserve(User::factory()->create(), $this->tomorrowB, 1, PaymentMethod::PayNow);
    });

    it('cancels them when a no-show is the third strike', function () {
        expect($this->tomorrowA->fresh()->status)->toBe(ListingStatus::SoldOut);

        $this->travelTo($this->missed->expires_at->addMinute());
        reservations()->markNoShow($this->missed);

        expect($this->customer->fresh()->isBlocked())->toBeTrue()
            ->and($this->paid->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($this->unpaid->fresh()->status)->toBe(ReservationStatus::Cancelled)
            // Not the customer's choice: not late, and refunded if paid.
            ->and($this->paid->fresh()->late_cancel)->toBeFalse()
            ->and($this->paid->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
            ->and($this->unpaid->fresh()->payment_status)->toBe(PaymentStatus::Unpaid)
            // No extra strikes on top of the block.
            ->and($this->customer->fresh()->no_show_strikes)->toBe(0)
            // The portions are back on sale.
            ->and($this->tomorrowA->fresh()->quantity_available)->toBe(2)
            ->and($this->tomorrowA->fresh()->status)->toBe(ListingStatus::Live)
            ->and($this->tomorrowB->fresh()->quantity_available)->toBe(4)
            // Other customers are not touched.
            ->and($this->someoneElses->fresh()->status)->toBe(ReservationStatus::Reserved);
    });

    it('cancels them when a late cancel is the third strike', function () {
        $this->travel(50)->minutes();

        $cancelled = reservations()->cancel($this->missed);

        expect($cancelled->late_cancel)->toBeTrue()
            ->and($this->customer->fresh()->isBlocked())->toBeTrue()
            ->and($this->paid->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($this->unpaid->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($this->customer->fresh()->no_show_strikes)->toBe(0);
    });

    it('keeps them when the strike does not lead to a block', function () {
        $this->customer->forceFill(['no_show_strikes' => 0])->save();
        $this->travelTo($this->missed->expires_at->addMinute());

        reservations()->markNoShow($this->missed);

        expect($this->customer->fresh()->isBlocked())->toBeFalse()
            ->and($this->paid->fresh()->status)->toBe(ReservationStatus::Reserved)
            ->and($this->unpaid->fresh()->status)->toBe(ReservationStatus::Reserved);
    });

    it('does it in one run of reservations:expire', function () {
        $this->travelTo($this->missed->expires_at->addMinute());

        $this->artisan('reservations:expire')->expectsOutput('Marked 1 reservation(s) as no-show.');

        expect($this->paid->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($this->unpaid->fresh()->status)->toBe(ReservationStatus::Cancelled);
    });

    it('has a safety net: reservations:expire releases anything a blocked customer still holds', function () {
        // Blocked some other way (say, by an admin later on) while holding reservations.
        $this->customer->forceFill(['blocked_until' => now()->addDays(7)])->save();

        $this->artisan('reservations:expire')
            ->expectsOutput('Marked 0 reservation(s) as no-show.')
            ->expectsOutput('Cancelled 3 waiting reservation(s) of blocked customers.');

        expect($this->customer->fresh()->reservations()->where('status', ReservationStatus::Reserved)->count())->toBe(0)
            ->and($this->customer->fresh()->no_show_strikes)->toBe(2)
            ->and($this->someoneElses->fresh()->status)->toBe(ReservationStatus::Reserved);

        // Running it again finds nothing.
        $this->artisan('reservations:expire')->doesntExpectOutputToContain('blocked customers');
    });

    it('leaves reservations of a customer whose block is over', function () {
        $this->customer->forceFill(['blocked_until' => now()->subMinute()])->save();

        $this->artisan('reservations:expire');

        expect($this->paid->fresh()->status)->toBe(ReservationStatus::Reserved);
    });
});
