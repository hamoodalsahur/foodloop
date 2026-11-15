<?php

// Admin → Orders: the audit view of every order across all shops, one order
// with its timeline, and the override that marks a waiting order collected.

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefusalReason;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use App\Queries\AdminOrders;
use App\Queries\CustomerImpact;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->travelTo('2026-11-16 18:18:00');
    $this->admin = User::factory()->admin()->create(['name' => 'Admin Aziz']);
    $this->actingAs($this->admin);
});

/**
 * An order at a named shop for a named customer.
 *
 * @param  array<string, mixed>  $attributes
 */
function orderAt(string $shop, string $customer, string $code, array $attributes = [], string $state = 'reserved'): Reservation
{
    $seller = Seller::where('business_name', $shop)->first()
        ?? Seller::factory()->approved()->create(['business_name' => $shop]);
    $user = User::where('name', $customer)->first()
        ?? User::factory()->create(['name' => $customer, 'email' => str($customer)->slug('.').'@student.test']);

    $factory = Reservation::factory()
        ->for(Listing::factory()->for($seller)->window(60, 180)->create(['title' => 'Curry puffs', 'price_sen' => 450]))
        ->for($user, 'customer');

    $factory = match ($state) {
        'collected' => $factory->collected(),
        'cancelled' => $factory->cancelled(),
        'no_show' => $factory->noShow(),
        default => $factory,
    };

    return $factory->create(['pickup_code' => $code, ...$attributes]);
}

describe('who gets in', function () {
    it('keeps everyone but admins out of the orders pages and the override', function (string $method, string $url) {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721');
        $url = str_replace('{order}', (string) $order->id, $url);

        auth()->logout();
        $this->{$method}($url)->assertRedirect('/login');

        $this->actingAs($order->customer)->{$method}($url)->assertForbidden();
        $this->actingAs($order->listing->seller->user)->{$method}($url)->assertForbidden();

        expect($order->fresh()->status)->toBe(ReservationStatus::Reserved);
    })->with([
        ['get', '/admin/orders'],
        ['get', '/admin/orders/{order}'],
        ['post', '/admin/orders/{order}/collect'],
    ]);

    it('has Orders in the admin menu', function () {
        $this->get('/admin')->assertOk()->assertSee('href="'.route('admin.orders.index').'"', false);
        $this->get('/admin/orders')->assertOk()->assertSee('aria-current="page"', false);
    });

    it('lets only admins use the override, by policy', function () {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721');

        expect($this->admin->can('collectAsAdmin', $order))->toBeTrue()
            ->and($order->customer->can('collectAsAdmin', $order))->toBeFalse()
            ->and($order->listing->seller->user->can('collectAsAdmin', $order))->toBeFalse();
    });
});

describe('the list', function () {
    beforeEach(function () {
        orderAt('Roti Lane Bakery', 'Aina Rahman', '1111', ['quantity' => 2, 'total_sen' => 900, 'created_at' => '2026-11-16 17:00:00']);
        orderAt('Roti Lane Bakery', 'Hafiz Musa', '2222', ['quantity' => 1, 'total_sen' => 450, 'created_at' => '2026-11-15 12:00:00', 'collected_at' => '2026-11-15 19:30:00'], 'collected');
        orderAt('Kopi Sinar', 'Aina Rahman', '3333', ['quantity' => 3, 'total_sen' => 1350, 'created_at' => '2026-11-14 12:00:00', 'cancelled_at' => '2026-11-14 13:05:00'], 'cancelled');
        orderAt('Kopi Sinar', 'Mei Ling Tan', '4444', ['quantity' => 1, 'total_sen' => 450, 'created_at' => '2026-11-13 12:00:00', 'expires_at' => '2026-11-13 21:15:00'], 'no_show');
    });

    it('opens on the waiting orders, with what an audit needs on each row', function () {
        $this->get('/admin/orders')
            ->assertOk()
            ->assertSeeInOrder(['Orders', 'Every order across all shops'])
            ->assertSeeInOrder(['1111', 'Aina R.', 'aina.rahman@student.test', 'Curry puffs × 2', 'Roti Lane Bakery', 'RM9', 'Pay at pickup', 'Reserved', '16 Nov, 5:00 PM', 'Not yet'])
            ->assertDontSee('2222')
            ->assertDontSee('3333')
            ->assertDontSee('4444');
    });

    it('shows one status per tab, and everything under All, newest first', function (string $tab, array $shown, array $hidden) {
        $response = $this->get('/admin/orders?tab='.$tab)->assertOk()->assertSeeInOrder($shown);

        foreach ($hidden as $code) {
            $response->assertDontSee($code);
        }
    })->with([
        'waiting' => ['waiting', ['1111', 'Reserved'], ['2222', '3333', '4444']],
        'collected' => ['collected', ['2222', 'Hafiz M.', 'Collected', '15 Nov, 7:30 PM'], ['1111', '3333', '4444']],
        'cancelled' => ['cancelled', ['3333', 'Kopi Sinar', 'Cancelled', '14 Nov, 1:05 PM'], ['1111', '2222', '4444']],
        'no-shows' => ['no-shows', ['4444', 'Mei L.', 'No-show', '13 Nov, 9:15 PM'], ['1111', '2222', '3333']],
        'all' => ['all', ['1111', '2222', '3333', '4444'], []],
    ]);

    it('falls back to the waiting tab for a tab that does not exist', function () {
        $this->get('/admin/orders?tab=everything')->assertOk()->assertSee('1111')->assertDontSee('2222');
    });

    it('adds up orders, money and portions for the tab shown', function (string $tab, array $totals) {
        expect((new AdminOrders($tab))->totals())->toBe($totals);
    })->with([
        'waiting' => ['waiting', ['orders' => 1, 'sen' => 900, 'portions' => 2]],
        'collected' => ['collected', ['orders' => 1, 'sen' => 450, 'portions' => 1]],
        'all' => ['all', ['orders' => 4, 'sen' => 3150, 'portions' => 7]],
    ]);

    it('prints the totals and the number on every tab', function () {
        $this->get('/admin/orders?tab=all')
            ->assertSeeInOrder(['Waiting', '1', 'Collected', '1', 'Cancelled', '1', 'No-shows', '1', 'All', '4'])
            ->assertSeeInOrder(['Orders', '4', 'Money', 'RM31.50', 'Portions', '7']);
    });

    it('finds orders by pickup code, customer name, email or shop', function (string $search, array $shown, array $hidden) {
        $response = $this->get('/admin/orders?tab=all&q='.urlencode($search))->assertOk();

        foreach ($shown as $code) {
            $response->assertSee($code);
        }
        foreach ($hidden as $code) {
            $response->assertDontSee($code);
        }
    })->with([
        'pickup code' => ['2222', ['2222'], ['1111', '3333', '4444']],
        'customer name' => ['aina', ['1111', '3333'], ['2222', '4444']],
        'customer email' => ['mei.ling.tan@student', ['4444'], ['1111', '2222', '3333']],
        'shop name' => ['kopi sin', ['3333', '4444'], ['1111', '2222']],
        'spaces around it' => ['  Hafiz ', ['2222'], ['1111', '3333', '4444']],
    ]);

    it('searches inside the tab, and the totals and tab numbers follow the search', function () {
        $this->get('/admin/orders?tab=cancelled&q=aina')
            ->assertSee('3333')
            ->assertDontSee('1111')
            ->assertSeeInOrder(['Waiting', '1', 'Collected', '0', 'Cancelled', '1', 'No-shows', '0', 'All', '2'])
            ->assertSeeInOrder(['Orders', '1', 'Money', 'RM13.50', 'Portions', '3'])
            ->assertSee('every order that matches “aina”');
    });

    it('treats % and _ in the search as plain characters', function () {
        expect((new AdminOrders('all', '%'))->totals()['orders'])->toBe(0)
            ->and((new AdminOrders('all', '_'))->totals()['orders'])->toBe(0);
    });

    it('says so when nothing matches, and offers the All tab', function () {
        $this->get('/admin/orders?q=nobody-here')
            ->assertOk()
            ->assertSee('No orders match “nobody-here”')
            ->assertSee('Look in all orders')
            ->assertSeeInOrder(['Orders', '0', 'Money', 'RM0', 'Portions', '0']);
    });

    it('keeps the tab and the search in every link', function () {
        $this->get('/admin/orders?tab=all&q=aina')
            ->assertSee('value="aina"', false)
            ->assertSee('name="tab" value="all"', false)
            ->assertSee(e(route('admin.orders.index', ['tab' => 'collected', 'q' => 'aina'])), false)
            ->assertSee(e(route('admin.orders.index', ['q' => 'aina'])), false);
    });
});

describe('pages', function () {
    it('shows 25 orders a page, newest first, and keeps tab and search while paging', function () {
        $listing = Listing::factory()->for(Seller::factory()->approved()->create(['business_name' => 'Roti Lane Bakery']))->create();

        foreach (range(1, 30) as $i) {
            Reservation::factory()->for($listing)->collected()->create([
                'pickup_code' => str_pad((string) (5000 + $i), 4, '0', STR_PAD_LEFT),
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $this->get('/admin/orders?tab=collected&q=roti')
            ->assertOk()
            ->assertSeeInOrder(['5001', '5002', '5025'])
            ->assertDontSee('5026')
            ->assertSee('Showing 1–25 of 30')
            ->assertSee('page 1 of 2')
            ->assertSee('Older')
            ->assertSee(e(route('admin.orders.index', ['tab' => 'collected', 'q' => 'roti', 'page' => 2])), false)
            // The totals cover all 30, not only the 25 on the page.
            ->assertSeeInOrder(['Orders', '30', 'Money']);

        $this->get('/admin/orders?tab=collected&q=roti&page=2')
            ->assertOk()
            ->assertSeeInOrder(['5026', '5030'])
            ->assertDontSee('5025')
            ->assertSee('Showing 26–30 of 30')
            ->assertSee('Newer');
    });

    it('is empty and friendly before anyone has reserved', function () {
        $this->get('/admin/orders?tab=all')->assertOk()->assertSee('No orders here');
    });
});

describe('one order', function () {
    it('shows the facts and where to look next', function () {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721', ['quantity' => 2, 'total_sen' => 900, 'created_at' => '2026-11-16 17:00:00']);

        $this->get('/admin/orders/'.$order->id)
            ->assertOk()
            ->assertSeeInOrder(['Order 4721', '2 × Curry puffs · Roti Lane Bakery'])
            ->assertSeeInOrder(['Pickup code', '4721', 'Amount', 'RM9', 'Portions', '2 × RM4.50', 'Payment', 'Pay at pickup', 'Pickup window', 'Today 7:18–9:18 PM', 'Held until', 'Mon 16 Nov, 9:33 PM'])
            ->assertSeeInOrder(['Aina Rahman', 'aina.rahman@student.test', '0 strikes', 'All orders by this customer'])
            ->assertSee(e(route('admin.orders.index', ['tab' => 'all', 'q' => 'aina.rahman@student.test'])), false)
            ->assertSee(route('listings.show', $order->listing), false)
            ->assertSee(e(route('admin.orders.index', ['tab' => 'all', 'q' => 'Roti Lane Bakery'])), false)
            ->assertDontSee('Strikes and limits');
    });

    it('links to the strikes page for a customer who has any', function () {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721');
        $order->customer->forceFill(['no_show_strikes' => 2])->save();

        $this->get('/admin/orders/'.$order->id)->assertSeeInOrder(['2 strikes', 'Strikes and limits']);
    });

    it('tells the story of a waiting pay-now order', function () {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721', [
            'created_at' => '2026-11-16 17:00:00',
            'payment_method' => PaymentMethod::PayNow, 'payment_status' => PaymentStatus::SimulatedPaid,
        ]);

        $this->get('/admin/orders/'.$order->id)->assertSeeInOrder([
            'What happened',
            'Reserved', 'Mon 16 Nov, 5:00 PM', '1 × Curry puffs for RM4.50, pickup code 4721.',
            'Paid (simulated)', 'Mon 16 Nov, 5:00 PM',
            'Waiting for pickup', 'Not yet', 'Pickup Today 7:18–9:18 PM. Held until Mon 16 Nov, 9:33 PM',
        ]);
    });

    it('tells the story of a normal pickup at the shop', function () {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721', ['created_at' => '2026-11-15 17:00:00', 'collected_at' => '2026-11-15 19:40:00'], 'collected');

        $this->get('/admin/orders/'.$order->id)
            ->assertSeeInOrder(['Reserved', 'Sun 15 Nov, 5:00 PM', 'Collected at the shop', 'Sun 15 Nov, 7:40 PM', 'Paid at pickup (simulated)', 'Sun 15 Nov, 7:40 PM'])
            ->assertDontSee('admin override')
            ->assertDontSee('Admin override');
    });

    it('tells the story of a cancelled, refunded order and of a late cancel', function () {
        $refunded = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721', [
            'created_at' => '2026-11-15 17:00:00', 'cancelled_at' => '2026-11-15 17:03:00',
            'payment_method' => PaymentMethod::PayNow, 'payment_status' => PaymentStatus::Refunded,
        ], 'cancelled');
        $late = orderAt('Roti Lane Bakery', 'Hafiz Musa', '4722', ['cancelled_at' => '2026-11-15 18:55:00', 'late_cancel' => true], 'cancelled');

        $this->get('/admin/orders/'.$refunded->id)
            ->assertSeeInOrder(['Reserved', 'Paid (simulated)', 'Cancelled', 'Sun 15 Nov, 5:03 PM', 'No strike.', 'Refunded (simulated)', 'Sun 15 Nov, 5:03 PM'])
            ->assertDontSee('Mark collected');

        $this->get('/admin/orders/'.$late->id)
            ->assertSeeInOrder(['Cancelled late', 'Sun 15 Nov, 6:55 PM', 'it counted as a strike'])
            ->assertDontSee('Refunded (simulated)');
    });

    it('tells the story of a no-show', function () {
        $order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721', ['created_at' => '2026-11-13 12:00:00', 'expires_at' => '2026-11-13 21:15:00'], 'no_show');

        $this->get('/admin/orders/'.$order->id)
            ->assertSeeInOrder(['Reserved', 'Fri 13 Nov, 12:00 PM', 'No-show', 'Fri 13 Nov, 9:15 PM', 'One strike for the customer'])
            ->assertDontSee('Mark collected');
    });

    it('answers 404 for an order that does not exist', function () {
        $this->get('/admin/orders/999')->assertNotFound();
    });
});

describe('the override', function () {
    beforeEach(function () {
        // Pickup window opens in an hour: the shop itself could not collect this yet.
        $this->order = orderAt('Roti Lane Bakery', 'Aina Rahman', '4721', ['quantity' => 2, 'total_sen' => 900]);
        $this->shopOwner = $this->order->listing->seller->user;
    });

    it('asks first: the button leads to a confirm step, and only that step has the form', function () {
        $url = '/admin/orders/'.$this->order->id;

        $this->get($url)
            ->assertSee('Mark collected (admin override)')
            ->assertSee(e(route('admin.orders.show', [$this->order, 'confirm' => 'collect'])), false)
            ->assertDontSee('Yes, mark collected')
            ->assertDontSee(route('admin.orders.collect', $this->order), false);

        $this->get($url.'?confirm=collect')
            ->assertSeeInOrder(['Mark order 4721 collected now?', 'It can’t be undone.', 'Yes, mark collected', 'No, keep it waiting'])
            ->assertSee('action="'.route('admin.orders.collect', $this->order).'"', false)
            ->assertSee('name="_token"', false);

        expect($this->order->fresh()->status)->toBe(ReservationStatus::Reserved);
    });

    it('does nothing without the confirm step', function () {
        $this->from('/admin/orders/'.$this->order->id)
            ->post('/admin/orders/'.$this->order->id.'/collect')
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHasErrors('confirmed');

        expect($this->order->fresh()->status)->toBe(ReservationStatus::Reserved);
    });

    it('marks a waiting order collected before its pickup window, and records who did it', function () {
        // The shop is refused at this moment…
        expectRefusal(fn () => reservations()->collect($this->order->listing->seller, '4721'), RefusalReason::TooEarly);

        // …the admin is not.
        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1'])
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHas('status', 'Order 4721 is marked collected (admin override). Aina R. and the shop have been told.');

        $order = $this->order->fresh();

        expect($order->status)->toBe(ReservationStatus::Collected)
            ->and($order->collected_at->equalTo(now()))->toBeTrue()
            ->and($order->collected_by_admin_id)->toBe($this->admin->id)
            ->and($order->collectedByAdmin->is($this->admin))->toBeTrue()
            ->and($order->isAdminOverride())->toBeTrue()
            // Pay at pickup counts as paid at that moment, as in a normal pickup.
            ->and($order->payment_status)->toBe(PaymentStatus::SimulatedPaid)
            // Collecting never moves stock.
            ->and($order->listing->fresh()->quantity_available)->toBe($this->order->listing->quantity_available);
    });

    it('also works after the pickup time has run out, as long as the order is still waiting', function () {
        $this->travelTo($this->order->expires_at->copy()->addHour());

        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1'])->assertSessionHasNoErrors();

        expect($this->order->fresh()->status)->toBe(ReservationStatus::Collected)
            ->and($this->order->customer->fresh()->no_show_strikes)->toBe(0);

        // The every-minute clean-up leaves it alone afterwards.
        expect(reservations()->expireOverdue())->toBe(0);
    });

    it('sends the customer the normal collected message and tells the shop', function () {
        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1']);

        expect(messageFor($this->order->customer, 'reservation.collected'))
            ->title->toBe('Collected: Curry puffs')
            ->body->toContain('Thanks for rescuing 2 portions')
            ->and(messageFor($this->shopOwner, 'seller.collected_by_admin'))
            ->title->toBe('FoodLoop marked code 4721 collected')
            ->body->toContain('2 × Curry puffs for Aina R.')
            ->url->toBe('/seller/pickups');
    });

    it('counts in the customer\'s impact and the platform numbers like any pickup', function () {
        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1']);

        expect((new CustomerImpact($this->order->customer->fresh()))->mealsRescued())->toBe(2);

        $this->get('/admin')->assertSeeInOrder(['Meals rescued this month', '2']);
    });

    it('shows up as an override in the list, on the order and in its timeline', function () {
        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1']);

        $this->get('/admin/orders?tab=collected')->assertSeeInOrder(['4721', 'Collected', 'Admin override']);

        $this->get('/admin/orders/'.$this->order->id)
            ->assertSeeInOrder(['Collected', 'Admin override', 'What happened', 'Reserved', 'Collected by Admin Aziz (admin override)', 'Mon 16 Nov, 6:18 PM', 'Paid at pickup (simulated)'])
            ->assertDontSee('Collected at the shop')
            ->assertDontSee('Mark collected (admin override)');
    });

    it('does not label a normal pickup as an override', function () {
        $this->travel(90)->minutes();
        reservations()->collect($this->order->listing->seller, '4721');

        expect($this->order->fresh()->isAdminOverride())->toBeFalse();

        $this->get('/admin/orders?tab=collected')->assertSee('4721')->assertDontSee('Admin override');
    });

    it('is safe to send twice: the second time changes nothing and says why', function () {
        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1'])->assertSessionHasNoErrors();
        $first = $this->order->fresh();

        $this->travel(5)->minutes();
        $other = User::factory()->admin()->create(['name' => 'Second Admin']);

        $this->actingAs($other)
            ->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1'])
            ->assertRedirect('/admin/orders/'.$this->order->id)
            ->assertSessionHasErrors(['admin' => 'Nothing changed: order 4721 is already collected. Only orders still waiting for pickup can be marked collected.']);

        $second = $this->order->fresh();

        expect($second->collected_at->equalTo($first->collected_at))->toBeTrue()
            ->and($second->collected_by_admin_id)->toBe($this->admin->id)
            ->and(messageKinds($this->order->customer))->toBe(['reservation.collected'])
            ->and(messageKinds($this->shopOwner))->toBe(['seller.collected_by_admin']);
    });

    it('refuses anything that is not waiting, with a clear message', function (string $state, string $word) {
        $order = orderAt('Kopi Sinar', 'Hafiz Musa', '9001', [], $state);
        $before = $order->fresh()->toArray();

        $this->post('/admin/orders/'.$order->id.'/collect', ['confirmed' => '1'])
            ->assertRedirect('/admin/orders/'.$order->id)
            ->assertSessionHasErrors(['admin' => "Nothing changed: order 9001 is already {$word}. Only orders still waiting for pickup can be marked collected."]);

        expect($order->fresh()->toArray())->toBe($before)
            ->and(messageKinds($order->customer))->toBe([]);

        // The message is shown on the order's page, and the confirm step is gone even when asked for.
        $this->followingRedirects()
            ->post('/admin/orders/'.$order->id.'/collect', ['confirmed' => '1'])
            ->assertSee("order 9001 is already {$word}");
        $this->get('/admin/orders/'.$order->id.'?confirm=collect')->assertDontSee('Yes, mark collected');
    })->with([
        'collected' => ['collected', 'collected'],
        'cancelled' => ['cancelled', 'cancelled'],
        'no-show' => ['no_show', 'no-show'],
    ]);

    it('is refused by the engine for anyone who is not an admin, whatever the page says', function () {
        expect(fn () => reservations()->collectAsAdmin($this->order, $this->shopOwner))->toThrow(LogicException::class)
            ->and(fn () => reservations()->collectAsAdmin($this->order, $this->order->customer))->toThrow(LogicException::class)
            ->and($this->order->fresh()->status)->toBe(ReservationStatus::Reserved);
    });

    it('keeps the order, without the name, when the admin account is deleted', function () {
        $this->post('/admin/orders/'.$this->order->id.'/collect', ['confirmed' => '1']);

        $other = User::factory()->admin()->create();
        $this->admin->delete();

        expect($this->order->fresh()->status)->toBe(ReservationStatus::Collected)
            ->and($this->order->fresh()->collected_by_admin_id)->toBeNull();

        $this->actingAs($other)->get('/admin/orders/'.$this->order->id)->assertOk();
    });

    it('changes nothing for sellers and customers: their own rules still apply', function () {
        // The shop still can't collect early…
        $this->actingAs($this->shopOwner)
            ->post('/seller/pickups/collect', ['code' => '4721'])
            ->assertSessionHasErrors(['code' => RefusalReason::TooEarly->label()]);

        // …and the customer's own order page works as before.
        $this->actingAs($this->order->customer)->get('/orders/'.$this->order->id)->assertOk()->assertSee('4721');

        expect($this->order->fresh()->status)->toBe(ReservationStatus::Reserved);
    });
});

describe('with the demo data', function () {
    it('lists the seeded orders and can override Aina\'s waiting code 4721', function () {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@foodloop.test')->firstOrFail();
        $order = Reservation::where('pickup_code', '4721')->where('status', ReservationStatus::Reserved)->firstOrFail();

        // No seeded order is an override: the demo data is left as it was.
        expect(Reservation::whereNotNull('collected_by_admin_id')->count())->toBe(0);

        $this->actingAs($admin)->get('/admin/orders?q=4721')->assertOk()->assertSeeInOrder(['4721', 'Aina R.', 'Bakery Surprise Bundle']);

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/collect', ['confirmed' => '1'])->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(ReservationStatus::Collected)
            ->and($order->fresh()->collected_by_admin_id)->toBe($admin->id);

        // demo:refresh keeps the collected order (history) and puts a fresh 4721 back.
        $this->artisan('demo:refresh')->assertSuccessful();

        expect($order->fresh()->status)->toBe(ReservationStatus::Collected)
            ->and(Reservation::where('pickup_code', '4721')->where('status', ReservationStatus::Reserved)->count())->toBe(1);
    });
});
