<?php

// Business rules from docs/BUILD_PLAN.md §3 that live on the Listing model.

use App\Enums\HalalStatus;
use App\Enums\ListingStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Listing;
use App\Models\Seller;
use Illuminate\Database\QueryException;

it('requires a halal status on every listing', function () {
    Listing::factory()->create(['halal_status' => null]);
})->throws(QueryException::class);

it('lets a shop with an approved certificate list as halal certified', function () {
    $listing = Listing::factory()->certified()->create();

    expect($listing->halal_status)->toBe(HalalStatus::Certified)
        ->and($listing->seller->hasApprovedHalalCertificate())->toBeTrue();
});

it('refuses "certified" when the shop has no certificate on file', function () {
    $seller = Seller::factory()->approved()->create(['halal_claim' => HalalStatus::Certified, 'halal_cert_path' => null]);

    Listing::factory()->for($seller)->create(['halal_status' => HalalStatus::Certified]);
})->throws(BusinessRuleViolation::class, 'approved halal certificate');

it('refuses "certified" when the shop does not claim to be certified', function () {
    $seller = Seller::factory()->approved()->create();

    Listing::factory()->for($seller)->create(['halal_status' => HalalStatus::Certified]);
})->throws(BusinessRuleViolation::class);

it('refuses "certified" while the shop and its certificate are not approved yet', function () {
    $seller = Seller::factory()->certified()->create();

    Listing::factory()->draft()->for($seller)->create(['halal_status' => HalalStatus::Certified]);
})->throws(BusinessRuleViolation::class);

it('refuses to change an existing listing to "certified" without a certificate', function () {
    $listing = Listing::factory()->create();

    $listing->update(['halal_status' => HalalStatus::Certified]);
})->throws(BusinessRuleViolation::class);

it('lets a shop that is not approved yet prepare a draft', function () {
    $listing = Listing::factory()->draft()->for(Seller::factory())->create();

    expect($listing->status)->toBe(ListingStatus::Draft);
});

it('refuses to put a listing live for a shop that is not approved', function (string $state) {
    $listing = Listing::factory()->draft()->for(Seller::factory()->{$state}())->create();

    expect(fn () => $listing->update(['status' => ListingStatus::Live]))
        ->toThrow(BusinessRuleViolation::class, 'approved the seller');

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);
})->with(['rejected' => 'rejected', 'suspended' => 'suspended']);

it('refuses to put a listing live for a shop still waiting for approval', function () {
    $listing = Listing::factory()->draft()->for(Seller::factory())->create();

    expect(fn () => $listing->update(['status' => ListingStatus::Live]))
        ->toThrow(BusinessRuleViolation::class, 'approved the seller');

    expect(fn () => Listing::factory()->for(Seller::factory())->create())
        ->toThrow(BusinessRuleViolation::class);
});

it('lets an approved shop put a listing live', function () {
    $listing = Listing::factory()->draft()->create();

    $listing->update(['status' => ListingStatus::Live, 'published_at' => now()]);

    expect($listing->fresh()->status)->toBe(ListingStatus::Live);
});

it('still lets other fields change on a live listing after its shop is suspended', function () {
    $listing = Listing::factory()->create();
    $listing->seller->forceFill(['status' => 'suspended'])->save();

    $listing->refresh()->update(['status' => ListingStatus::Paused]);

    expect($listing->fresh()->status)->toBe(ListingStatus::Paused);
});
