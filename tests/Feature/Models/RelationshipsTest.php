<?php

use App\Enums\HalalStatus;
use App\Enums\ListingCategory;
use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReportStatus;
use App\Enums\ReservationStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Donation;
use App\Models\FoodBankPartner;
use App\Models\Listing;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

it('links a seller account to its one shop', function () {
    $seller = Seller::factory()->create();

    expect($seller->user->role)->toBe(UserRole::Seller)
        ->and($seller->user->seller->is($seller))->toBeTrue();
});

it('allows only one shop per seller account', function () {
    $seller = Seller::factory()->create();

    Seller::factory()->create(['user_id' => $seller->user_id]);
})->throws(QueryException::class);

it('links a shop to its listings', function () {
    $seller = Seller::factory()->approved()->create();
    $listings = Listing::factory()->count(2)->for($seller)->create();

    expect($seller->listings)->toHaveCount(2)
        ->and($listings->first()->seller->is($seller))->toBeTrue();
});

it('links a reservation to its listing and its customer', function () {
    $customer = User::factory()->create();
    $listing = Listing::factory()->create();
    $reservation = Reservation::factory()->for($listing)->create(['customer_id' => $customer->id]);

    expect($reservation->listing->is($listing))->toBeTrue()
        ->and($reservation->customer->is($customer))->toBeTrue()
        ->and($listing->reservations)->toHaveCount(1)
        ->and($customer->reservations)->toHaveCount(1);
});

it('links a report to its reporter, listing and the admin who closed it', function () {
    $report = Report::factory()->actioned()->create();

    expect($report->reporter->reports->first()->is($report))->toBeTrue()
        ->and($report->listing->reports)->toHaveCount(1)
        ->and($report->resolver->isAdmin())->toBeTrue()
        ->and($report->status)->toBe(ReportStatus::Actioned)
        ->and($report->resolved_at)->toBeInstanceOf(Carbon::class);
});

it('links a donation to its listing and food bank partner', function () {
    $partner = FoodBankPartner::factory()->create();
    $donation = Donation::factory()->create(['food_bank_partner_id' => $partner->id]);

    expect($donation->partner->is($partner))->toBeTrue()
        ->and($partner->donations)->toHaveCount(1)
        ->and($donation->listing->donations->first()->is($donation))->toBeTrue()
        ->and($donation->listing->status)->toBe(ListingStatus::Closed);
});

it('reads listing columns back as the right types', function () {
    $listing = Listing::factory()->bundle()->create([
        'category' => 'bakery',
        'allergens' => ['egg', 'milk', 'wheat'],
        'offer_leftovers' => 1,
    ])->fresh();

    expect($listing->type)->toBe(ListingType::Bundle)
        ->and($listing->category)->toBe(ListingCategory::Bakery)
        ->and($listing->halal_status)->toBe(HalalStatus::MuslimOwned)
        ->and($listing->status)->toBe(ListingStatus::Live)
        ->and($listing->allergens)->toBe(['egg', 'milk', 'wheat'])
        ->and($listing->offer_leftovers)->toBeTrue()
        ->and($listing->pickup_start)->toBeInstanceOf(Carbon::class)
        ->and($listing->pickup_end->greaterThan($listing->pickup_start))->toBeTrue()
        ->and($listing->price_sen)->toBeInt();
});

it('reads reservation columns back as the right types', function () {
    $reservation = Reservation::factory()->paidNow()->create()->fresh();

    expect($reservation->status)->toBe(ReservationStatus::Reserved)
        ->and($reservation->payment_method)->toBe(PaymentMethod::PayNow)
        ->and($reservation->payment_status)->toBe(PaymentStatus::SimulatedPaid)
        ->and($reservation->pickup_code)->toMatch('/^\d{4}$/')
        ->and($reservation->late_cancel)->toBeFalse();
});

it('sets a reservation to expire when the grace period after the pickup window ends', function () {
    $listing = Listing::factory()->create();
    $reservation = Reservation::factory()->for($listing)->create();

    expect($reservation->expires_at->equalTo(
        $listing->pickup_end->addMinutes(config('foodloop.grace_minutes'))
    ))->toBeTrue();
});

it('only returns live listings from the live scope', function () {
    Listing::factory()->create();
    Listing::factory()->draft()->create();
    Listing::factory()->paused()->create();
    Listing::factory()->soldOut()->create();
    Listing::factory()->closed()->create();

    expect(Listing::live()->count())->toBe(1);
});

it('starts a new shop as waiting for approval', function () {
    $seller = Seller::factory()->create()->fresh();

    expect($seller->status)->toBe(SellerStatus::Pending)
        ->and($seller->isApproved())->toBeFalse()
        ->and($seller->approved_at)->toBeNull();
});
