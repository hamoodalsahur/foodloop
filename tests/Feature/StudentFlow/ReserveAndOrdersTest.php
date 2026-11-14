<?php

// Reserving, the "You're booked" page with the pickup code (mockup c03),
// the orders list, and cancelling.

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:18:00');

    $this->customer = User::factory()->create(['name' => 'Aina Rahman']);
    $this->listing = dealAt(0.6, [
        'title' => 'Bakery Surprise Bundle',
        'price_sen' => 800,
        'original_price_sen' => 2000,
        'quantity_total' => 5,
        'quantity_available' => 5,
        'pickup_start' => '2026-11-16 19:00:00',
        'pickup_end' => '2026-11-16 21:00:00',
    ], 'Roti Lane Bakery');

    $this->reserve = fn (array $form = [], ?User $as = null) => $this
        ->actingAs($as ?? $this->customer)
        ->post(route('reservations.store', $this->listing), ['quantity' => 1, 'payment_method' => 'pay_now', ...$form]);
});

describe('reserving', function () {
    it('takes a customer from the reserve button to their pickup code', function () {
        $response = ($this->reserve)(['quantity' => 2, 'payment_method' => 'pay_at_pickup']);

        $reservation = Reservation::sole();
        $response->assertRedirect(route('orders.show', $reservation));

        expect($reservation->customer->is($this->customer))->toBeTrue()
            ->and($reservation->quantity)->toBe(2)
            ->and($reservation->total_sen)->toBe(1600)
            ->and($reservation->payment_status)->toBe(PaymentStatus::Unpaid)
            ->and($this->listing->fresh()->quantity_available)->toBe(3);

        $this->get(route('orders.show', $reservation))
            ->assertOk()
            ->assertSeeInOrder(['Reserved', 'You’re booked.', 'Show this code at Roti Lane Bakery.', 'Pickup', 'code', $reservation->pickup_code]);
    });

    it('checks the form', function (array $form, string $field, string $message) {
        ($this->reserve)($form)->assertSessionHasErrors([$field => $message]);

        expect(Reservation::count())->toBe(0);
    })->with([
        'no quantity' => [['quantity' => ''], 'quantity', 'Choose how many you want.'],
        'zero' => [['quantity' => 0], 'quantity', 'Choose at least 1.'],
        'too many' => [['quantity' => 4], 'quantity', 'You can reserve up to 3 of each listing.'],
        'not a number' => [['quantity' => 'two'], 'quantity', 'Choose a whole number.'],
        'no payment method' => [['payment_method' => ''], 'payment_method', 'Choose how you want to pay.'],
        'made-up payment method' => [['payment_method' => 'bitcoin'], 'payment_method', 'Choose how you want to pay.'],
    ]);

    it('sends a guest to log in first', function () {
        $this->post(route('reservations.store', $this->listing), ['quantity' => 1, 'payment_method' => 'pay_now'])
            ->assertRedirect('/login');

        expect(Reservation::count())->toBe(0);
    });

    it('does not let sellers or admins reserve', function (string $state) {
        ($this->reserve)(as: User::factory()->{$state}()->create())->assertForbidden();

        expect(Reservation::count())->toBe(0);
    })->with(['seller', 'admin']);

    it('brings a refusal back to the listing page as a message', function (Closure $arrange, string $message) {
        $arrange($this);
        $before = Reservation::count();

        ($this->reserve)()->assertRedirect(route('listings.show', $this->listing));

        // (In a real browser the signed-in user is read fresh on every page; in a test we have to do it.)
        $this->actingAs($this->customer->fresh())
            ->get(route('listings.show', $this->listing))
            ->assertSee($message);

        expect(Reservation::count())->toBe($before);
    })->with([
        'sold out while they were looking' => [
            fn ($test) => $test->listing->update(['quantity_available' => 0, 'status' => 'sold_out']),
            'Sold out. Someone just took the last one.',
        ],
        'customer is blocked' => [
            fn ($test) => $test->customer->forceFill(['blocked_until' => now()->addDays(3)])->save(),
            'You can’t reserve right now because of missed pickups.',
        ],
        'pickup window has ended' => [
            fn ($test) => $test->travelTo('2026-11-16 21:00:01'),
            'The pickup window for this listing has ended.',
        ],
        'not enough left' => [
            fn ($test) => $test->listing->update(['quantity_available' => 0]),
            'There aren’t that many left.',
        ],
        'already holds three' => [
            fn ($test) => Reservation::factory()->for($test->listing)->create(['customer_id' => $test->customer->id, 'quantity' => 3]),
            'You already have some of this listing. The limit is 3 per person.',
        ],
        'shop was suspended' => [
            fn ($test) => $test->listing->seller->forceFill(['status' => 'suspended'])->save(),
            'This seller isn’t taking reservations right now.',
        ],
    ]);

    it('keeps the quantity the customer picked when the form comes back', function () {
        $this->listing->update(['quantity_available' => 1]);

        ($this->reserve)(['quantity' => 2])->assertSessionHasInput('quantity', '2');
    });
});

describe('the booked page', function () {
    beforeEach(function () {
        ($this->reserve)();
        $this->reservation = Reservation::sole();
        $this->page = route('orders.show', $this->reservation);
    });

    it('shows the details and the countdown to the pickup window', function () {
        $this->get($this->page)
            ->assertSeeInOrder([
                'Pickup window opens in', '00:42:00',
                'Reserved', 'Window open', 'Collected',
                'Item', 'Bakery Surprise Bundle (1 portion)',
                'Pickup', 'Today 7–9 PM',
                'Where', 'Roti Lane Bakery · 0.6 km',
                'Payment', 'RM8 · Paid (simulated)',
            ])
            ->assertSee('Directions')
            ->assertSee('https://www.google.com/maps/dir/', false);
    });

    it('says until when cancelling is free, as in the mockup', function () {
        $this->get($this->page)
            ->assertSee('Free cancellation until')
            ->assertSee('6:30 PM')
            ->assertSee('After that it counts as a no-show.')
            ->assertSee('It’s free right now.');
    });

    it('warns that cancelling is late once the free time is over', function () {
        $this->travelTo('2026-11-16 18:30:01');

        $this->get($this->page)
            ->assertSee('Cancelling now counts as a no-show.')
            ->assertSee('This is a late cancel')
            ->assertDontSee('Free cancellation until');
    });

    it('uses the 5-minute "changed my mind" time when someone reserves late', function () {
        $this->travelTo('2026-11-16 19:30:00');
        ($this->reserve)(as: $late = User::factory()->create());

        $this->get(route('orders.show', $late->reservations()->sole()))
            ->assertSee('Free cancellation until')
            ->assertSee('7:35 PM');
    });

    it('follows the pickup through its steps', function (string $moment, string $label, string $clock) {
        $this->travelTo($moment);

        $this->get($this->page)->assertSeeInOrder([$label, $clock]);
    })->with([
        'window open' => ['2026-11-16 19:30:00', 'Pickup window closes in', '01:30:00'],
        'grace period' => ['2026-11-16 21:05:00', 'Last chance: collect within', '00:10:00'],
    ]);

    it('shows what happened once it is collected, cancelled or missed', function (Closure $arrange, array $expected, string $notThere) {
        $arrange($this);

        $this->actingAs($this->customer->fresh())
            ->get($this->page)->assertOk()->assertSeeInOrder($expected)->assertDontSee($notThere)->assertSee('Find another deal');
    })->with([
        'collected' => [
            function ($test) {
                $test->travelTo('2026-11-16 19:10:00');
                reservations()->collect($test->listing->seller, $test->reservation->pickup_code);
            },
            ['Collected', 'Collected. Enjoy!', 'Picked up at Roti Lane Bakery today at 7:10 PM', 'RM8 · Paid (simulated)'],
            'Pickup window',
        ],
        'cancelled' => [
            fn ($test) => reservations()->cancel($test->reservation),
            ['Cancelled', 'Reservation cancelled.', 'RM8 · Refunded (simulated)'],
            'Yes, cancel',
        ],
        'missed' => [
            function ($test) {
                $test->travelTo('2026-11-16 21:16:00');
                reservations()->markNoShow($test->reservation);
            },
            ['No-show', 'You missed this pickup.', 'This counted as a no-show.', 'You have 1 of 3 strikes'],
            'Yes, cancel',
        ],
    ]);

    it('tells a customer who just got blocked until when', function () {
        $this->customer->forceFill(['no_show_strikes' => 2])->save();
        $this->travelTo('2026-11-16 21:16:00');
        reservations()->markNoShow($this->reservation);

        $this->actingAs($this->customer->fresh())
            ->get($this->page)->assertSee('You can’t reserve until')->assertSee('Mon 23 Nov, 9:16 PM');
    });

    it('is only for the customer who made the reservation', function () {
        $this->actingAs(User::factory()->create())->get($this->page)->assertForbidden();
        $this->actingAs($this->listing->seller->user)->get($this->page)->assertForbidden();
        auth()->logout();
        $this->get($this->page)->assertRedirect('/login');
    });
});

describe('cancelling', function () {
    beforeEach(function () {
        ($this->reserve)(['quantity' => 2]);
        $this->reservation = Reservation::sole();
    });

    it('cancels for free, puts the food back and says so', function () {
        $this->delete(route('orders.destroy', $this->reservation))
            ->assertRedirect(route('orders.show', $this->reservation))
            ->assertSessionHas('status', 'Reservation cancelled. No strike, and the food is back on sale.');

        expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($this->reservation->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
            ->and($this->listing->fresh()->quantity_available)->toBe(5)
            ->and($this->customer->fresh()->no_show_strikes)->toBe(0);

        $this->get(route('orders.show', $this->reservation))->assertSee('No strike, and the food is back on sale.');
    });

    it('gives a strike for a late cancel and says so', function () {
        $this->travelTo('2026-11-16 18:45:00');

        $this->delete(route('orders.destroy', $this->reservation))
            ->assertSessionHas('status', 'Reservation cancelled. Because it was a late cancel, it counts as a no-show.');

        expect($this->reservation->fresh()->late_cancel)->toBeTrue()
            ->and($this->customer->fresh()->no_show_strikes)->toBe(1);

        $this->actingAs($this->customer->fresh())
            ->get(route('orders.show', $this->reservation))->assertSee('You have 1 of 3 strikes');
    });

    it('does not let anyone else cancel it', function () {
        $this->actingAs(User::factory()->create())->delete(route('orders.destroy', $this->reservation))->assertForbidden();
        $this->actingAs($this->listing->seller->user)->delete(route('orders.destroy', $this->reservation))->assertForbidden();

        expect($this->reservation->fresh()->status)->toBe(ReservationStatus::Reserved);
    });

    it('explains when it can no longer be cancelled', function () {
        $this->travelTo('2026-11-16 19:10:00');
        reservations()->collect($this->listing->seller, $this->reservation->pickup_code);

        $this->delete(route('orders.destroy', $this->reservation))
            ->assertRedirect(route('orders.show', $this->reservation))
            ->assertSessionHasErrors(['reservation' => 'This reservation is already closed.']);
    });
});

describe('the orders list', function () {
    it('shows what is waiting for pickup first, then earlier orders, newest first', function () {
        ($this->reserve)();
        $code = Reservation::sole()->pickup_code;

        $older = Listing::factory()->closed()->create(['title' => 'Nasi ayam set', 'pickup_start' => '2026-11-09 18:00:00', 'pickup_end' => '2026-11-09 19:00:00']);
        $newer = Listing::factory()->closed()->create(['title' => 'Iced latte', 'pickup_start' => '2026-11-14 18:00:00', 'pickup_end' => '2026-11-14 19:00:00']);
        Reservation::factory()->for($older)->collected()->create(['customer_id' => $this->customer->id]);
        Reservation::factory()->for($newer)->noShow()->create(['customer_id' => $this->customer->id, 'quantity' => 2]);

        $this->get('/orders')
            ->assertOk()
            ->assertSeeInOrder([
                'Orders',
                'Waiting for pickup', 'Bakery Surprise Bundle', 'Roti Lane Bakery · Qty 1', 'Today 7–9 PM', 'Code', $code,
                'Earlier', 'Iced latte', 'Sat 14 Nov', 'Qty 2', 'No-show', 'Nasi ayam set', 'Mon 9 Nov', 'Collected',
            ]);
    });

    it('shows only the customer\'s own orders', function () {
        Reservation::factory()->for(Listing::factory()->create(['title' => 'Someone else’s kuih']))->create();

        $this->actingAs($this->customer)->get('/orders')
            ->assertDontSee('Someone else’s kuih')
            ->assertSee('No orders yet')
            ->assertSee('See tonight’s deals');
    });
});
