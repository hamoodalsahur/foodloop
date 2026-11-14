<?php

use App\Models\Listing;
use App\Models\Reservation;
use App\Support\Money;

it('writes sen as ringgit', function (int $sen, string $text) {
    expect(Money::format($sen))->toBe($text);
})->with([
    'whole ringgit has no decimals' => [800, 'RM8'],
    'sen always show two digits' => [450, 'RM4.50'],
    'five sen' => [5, 'RM0.05'],
    'zero' => [0, 'RM0'],
    'thousands get a comma' => [123456, 'RM1,234.56'],
    'a saving shown as negative' => [-650, '−RM6.50'],
]);

it('formats listing prices and works out the discount', function () {
    $listing = Listing::factory()->create(['original_price_sen' => 2000, 'price_sen' => 800]);

    expect($listing->price_formatted)->toBe('RM8')
        ->and($listing->original_price_formatted)->toBe('RM20')
        ->and($listing->saving_sen)->toBe(1200)
        ->and($listing->discount_percent)->toBe(60);
});

it('rounds the discount to a whole percent', function () {
    $listing = Listing::factory()->create(['original_price_sen' => 2400, 'price_sen' => 900]);

    expect($listing->discount_percent)->toBe(63);
});

it('formats a reservation total', function () {
    $listing = Listing::factory()->create(['price_sen' => 650]);
    $reservation = Reservation::factory()->for($listing)->create(['quantity' => 3]);

    expect($reservation->total_sen)->toBe(1950)
        ->and($reservation->total_formatted)->toBe('RM19.50');
});
