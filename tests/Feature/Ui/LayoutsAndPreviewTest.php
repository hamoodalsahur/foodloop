<?php

// The two layouts and the developer preview pages (/dev/..., local only).

use App\Models\Seller;
use App\Models\User;

function asLocal(): void
{
    app()->detectEnvironment(fn () => 'local');
}

describe('developer preview pages', function () {
    it('shows every component on /dev/components when running locally', function () {
        asLocal();

        $this->get('/dev/components')
            ->assertOk()
            ->assertSeeInOrder([
                'x-button', 'x-card', 'x-tag', 'x-status-pill', 'x-price-box', 'x-food-photo',
                'x-deal-row', 'x-stat-tile', 'x-spec-grid', 'x-notice', 'x-countdown', 'x-back-link', 'x-form.input',
                'x-empty-state', 'x-error-state',
            ])
            ->assertSee('sample data')
            ->assertSee('Try again');
    });

    it('shows both layout previews when running locally', function (string $url, array $expected) {
        asLocal();

        $this->get($url)->assertOk()->assertSee('Sample data')->assertSeeInOrder($expected);
    })->with([
        'customer' => ['/dev/layouts/customer', ['Deals', 'Map', 'Orders', 'Impact', 'Curry puffs × 4', 'RM3']],
        'dashboard' => ['/dev/layouts/dashboard', ['Seller', 'Roti Lane Bakery', 'Dashboard', 'Listings', 'Pickups', 'Tonight', '4721', 'Reserved']],
    ]);

    it('works on an empty database (the sample data is built in memory)', function () {
        asLocal();

        $this->get('/dev/components')->assertOk();

        expect(User::count())->toBe(0);
    });

    it('does not exist anywhere but local', function (string $environment) {
        app()->detectEnvironment(fn () => $environment);

        $this->get('/dev/components')->assertNotFound();
        $this->get('/dev/layouts/customer')->assertNotFound();
        $this->get('/dev/layouts/dashboard')->assertNotFound();
    })->with(['production', 'staging', 'testing']);
});

describe('customer layout', function () {
    it('marks the current page in the navigation, for screen readers too', function () {
        $this->blade('<x-layouts.customer title="Orders" active="orders">Hi</x-layouts.customer>')
            ->assertSee('<title>Orders · FoodLoop</title>', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Skip to content');
    });

    it('offers "Log in" to guests and shows initials when signed in', function () {
        $this->blade('<x-layouts.customer>Hi</x-layouts.customer>')->assertSee('Log in');

        $this->actingAs(User::factory()->create(['name' => 'Aina Rahman']));

        $this->blade('<x-layouts.customer>Hi</x-layouts.customer>')
            ->assertSee('AR')
            ->assertSee('Account menu for Aina Rahman')
            ->assertSee('Log out')
            ->assertDontSee('Log in');
    });

    it('greys out menu items whose page is not built yet instead of linking nowhere', function () {
        $this->blade('<x-layouts.customer active="deals">Hi</x-layouts.customer>')
            ->assertSee('href="'.route('deals.index').'"', false)
            ->assertSee('Map<span class="sr-only"> (coming soon)</span>', false)
            ->assertDontSee('href="#"', false);
    });

    it('can drop the logo bar and the bottom navigation for detail pages', function () {
        $this->blade('<x-layouts.customer :header="false" :nav="false" flush>Hi</x-layouts.customer>')
            ->assertDontSee('foodloop<span', false)
            ->assertDontSee('aria-label="Main"', false)
            ->assertSee('Skip to content');
    });

    it('has a "You’re offline" strip ready for when the connection drops', function () {
        $this->blade('<x-layouts.customer>Hi</x-layouts.customer>')
            ->assertSee('You’re offline')
            ->assertSee('@offline.window', false);
    });

    it('shows the dark notice strip only when a page sets one', function () {
        $this->blade('<x-layouts.customer>Hi</x-layouts.customer>')->assertDontSee('bg-marigold" aria-hidden', false);

        // (Keep a space or new line after </x-slot:...>: Blade needs it to see the closing tag.)
        $this->blade('<x-layouts.customer> <x-slot:notice>12 deals close within the hour</x-slot:notice> Hi </x-layouts.customer>')
            ->assertSee('12 deals close within the hour');
    });
});

describe('dashboard layouts', function () {
    it('shows the seller menu, the shop and a log-out button', function () {
        $seller = Seller::factory()->approved()->certified()->create(['business_name' => 'Roti Lane Bakery']);
        $this->actingAs($seller->user);

        $this->blade('<x-layouts.seller title="Tonight" active="pickups">Hi</x-layouts.seller>')
            ->assertSeeInOrder(['Seller', 'Roti Lane Bakery', 'Verified', 'Halal certified', 'Dashboard', 'Listings', 'Pickups', 'Donations', 'Shop profile'])
            ->assertSee('<h1', false)
            ->assertSee('Tonight')
            ->assertSee('Log out')
            ->assertSee('action="'.route('logout').'"', false);
    });

    it('shows the admin menu', function () {
        $this->actingAs(User::factory()->admin()->create());

        $this->blade('<x-layouts.admin title="Overview" active="overview">Hi</x-layouts.admin>')
            ->assertSeeInOrder(['Admin', 'Overview', 'Sellers', 'Reports', 'Customers']);
    });
});
