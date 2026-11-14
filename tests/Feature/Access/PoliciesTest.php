<?php

// What a signed-in person may do with ONE listing, reservation, shop or
// report (app/Policies). The headline rule: a seller can only touch their
// own listings and reservations.

use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;

beforeEach(function () {
    $this->shop = Seller::factory()->approved()->create();
    $this->owner = $this->shop->user;
    $this->otherSeller = Seller::factory()->approved()->create()->user;
    $this->customer = User::factory()->create();
    $this->admin = User::factory()->admin()->create();
    $this->listing = Listing::factory()->for($this->shop)->create();
});

describe('listings', function () {
    it('lets a seller change only their own shop\'s listings', function (string $ability) {
        expect($this->owner->can($ability, $this->listing))->toBeTrue()
            ->and($this->otherSeller->can($ability, $this->listing))->toBeFalse()
            ->and($this->customer->can($ability, $this->listing))->toBeFalse()
            ->and($this->admin->can($ability, $this->listing))->toBeFalse();
    })->with(['update', 'delete', 'pause', 'publish']);

    it('lets only an approved shop publish', function (string $state) {
        $shop = Seller::factory()->{$state}()->create();
        $draft = Listing::factory()->draft()->for($shop)->create();

        expect($shop->user->can('publish', $draft))->toBeFalse()
            // …but they can still prepare and edit the draft.
            ->and($shop->user->can('update', $draft))->toBeTrue()
            ->and($shop->user->can('create', Listing::class))->toBeTrue();
    })->with(['pending', 'rejected', 'suspended']);

    it('lets only sellers with a shop create listings', function () {
        $sellerWithoutShop = User::factory()->seller()->create();

        expect($this->owner->can('create', Listing::class))->toBeTrue()
            ->and($sellerWithoutShop->can('create', Listing::class))->toBeFalse()
            ->and($this->customer->can('create', Listing::class))->toBeFalse()
            ->and($this->admin->can('create', Listing::class))->toBeFalse();
    });

    it('shows live listings to everyone, including guests', function () {
        expect(Gate::forUser(null)->allows('view', $this->listing))->toBeTrue()
            ->and(Gate::forUser(null)->allows('viewAny', Listing::class))->toBeTrue()
            ->and($this->customer->can('view', $this->listing))->toBeTrue();
    });

    it('shows a draft only to its shop and to admins', function () {
        $draft = Listing::factory()->draft()->for($this->shop)->create();

        expect($this->owner->can('view', $draft))->toBeTrue()
            ->and($this->admin->can('view', $draft))->toBeTrue()
            ->and($this->otherSeller->can('view', $draft))->toBeFalse()
            ->and($this->customer->can('view', $draft))->toBeFalse()
            ->and(Gate::forUser(null)->allows('view', $draft))->toBeFalse();
    });

    it('lets only admins moderate a listing', function () {
        expect($this->admin->can('moderate', $this->listing))->toBeTrue()
            ->and($this->owner->can('moderate', $this->listing))->toBeFalse()
            ->and($this->customer->can('moderate', $this->listing))->toBeFalse();
    });
});

describe('reservations', function () {
    beforeEach(function () {
        $this->reservation = Reservation::factory()->for($this->listing)->create(['customer_id' => $this->customer->id]);
        $this->otherCustomer = User::factory()->create();
    });

    it('lets only customer accounts reserve', function () {
        expect($this->customer->can('create', Reservation::class))->toBeTrue()
            ->and($this->owner->can('create', Reservation::class))->toBeFalse()
            ->and($this->admin->can('create', Reservation::class))->toBeFalse();
    });

    it('lets only the customer who made it cancel', function () {
        expect($this->customer->can('cancel', $this->reservation))->toBeTrue()
            ->and($this->otherCustomer->can('cancel', $this->reservation))->toBeFalse()
            ->and($this->owner->can('cancel', $this->reservation))->toBeFalse()
            ->and($this->admin->can('cancel', $this->reservation))->toBeFalse();
    });

    it('lets only the shop it is for mark it collected', function () {
        expect($this->owner->can('collect', $this->reservation))->toBeTrue()
            ->and($this->otherSeller->can('collect', $this->reservation))->toBeFalse()
            ->and($this->customer->can('collect', $this->reservation))->toBeFalse()
            ->and($this->admin->can('collect', $this->reservation))->toBeFalse();
    });

    it('shows it to its customer, its shop and admins only', function () {
        expect($this->customer->can('view', $this->reservation))->toBeTrue()
            ->and($this->owner->can('view', $this->reservation))->toBeTrue()
            ->and($this->admin->can('view', $this->reservation))->toBeTrue()
            ->and($this->otherCustomer->can('view', $this->reservation))->toBeFalse()
            ->and($this->otherSeller->can('view', $this->reservation))->toBeFalse();
    });
});

describe('shops and reports', function () {
    it('lets a seller edit only their own shop profile, and only admins review shops', function () {
        expect($this->owner->can('update', $this->shop))->toBeTrue()
            ->and($this->otherSeller->can('update', $this->shop))->toBeFalse()
            ->and($this->admin->can('update', $this->shop))->toBeFalse()
            ->and($this->admin->can('review', $this->shop))->toBeTrue()
            ->and($this->owner->can('review', $this->shop))->toBeFalse()
            ->and($this->owner->can('view', $this->shop))->toBeTrue()
            ->and($this->admin->can('view', $this->shop))->toBeTrue()
            ->and($this->otherSeller->can('view', $this->shop))->toBeFalse();
    });

    it('lets customers report, and only admins resolve reports', function () {
        $report = Report::factory()->create(['reporter_id' => $this->customer->id, 'listing_id' => $this->listing->id]);

        expect($this->customer->can('create', Report::class))->toBeTrue()
            ->and($this->owner->can('create', Report::class))->toBeFalse()
            ->and($this->admin->can('resolve', $report))->toBeTrue()
            ->and($this->customer->can('resolve', $report))->toBeFalse()
            ->and($this->owner->can('resolve', $report))->toBeFalse()
            ->and($this->customer->can('view', $report))->toBeTrue()
            ->and($this->owner->can('view', $report))->toBeFalse();
    });
});
