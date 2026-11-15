<?php

// `php artisan demo:refresh` on a database that has no demo data in it
// (for example right after `php artisan migrate`, before seeding).

use App\Models\Listing;
use App\Models\User;

it('refuses politely when the demo data is not there', function () {
    $this->artisan('demo:refresh')
        ->expectsOutputToContain('admin@foodloop.test')
        ->expectsOutputToContain('php artisan migrate:fresh --seed')
        ->assertFailed();

    expect(Listing::count())->toBe(0)
        ->and(User::count())->toBe(0);
});

it('refuses when only some of it is there, and says what is missing', function () {
    User::factory()->create(['email' => 'admin@foodloop.test']);
    User::factory()->create(['email' => 'aina@foodloop.test']);

    $this->artisan('demo:refresh')
        ->expectsOutputToContain('the demo shop Bonda Mawar Cake House (bondamawar@foodloop.test)')
        ->expectsOutputToContain('the demo customer hafiz@foodloop.test')
        ->doesntExpectOutputToContain('aina@foodloop.test')
        ->assertFailed();

    expect(Listing::count())->toBe(0)
        ->and(User::count())->toBe(2);
});
