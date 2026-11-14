<?php

/*
| The "two students, one last item" test with real simultaneous requests:
| several separate PHP processes try to reserve at the same instant.
|
| It needs a real database server that all processes can share, so it only
| runs on MySQL:  php artisan test -c phpunit.mysql.xml
| On the default in-memory SQLite it is skipped (the same rule is still
| covered there by "does not oversell when two requests both saw the last
| item" in tests/Feature/Reservations/ReserveTest.php).
*/

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Needs MySQL: run "php artisan test -c phpunit.mysql.xml".');
    }

    // These tests commit real rows, so start from and leave behind an empty database.
    Artisan::call('migrate:fresh');
});

afterEach(function () {
    if (DB::getDriverName() === 'mysql') {
        Artisan::call('migrate:fresh');
    }
});

/**
 * Start one process per customer, fire them together, return what each printed.
 *
 * @return list<string>
 */
function raceToReserve(Listing $listing, int $customers, int $quantityEach): array
{
    $startAt = microtime(true) + 3;

    $processes = User::factory()->count($customers)->create()->map(function (User $customer) use ($listing, $quantityEach, $startAt) {
        $process = new Process(
            [PHP_BINARY, base_path('tests/Concurrency/reserve_worker.php'), $listing->id, $customer->id, $quantityEach, $startAt],
            base_path(),
            [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => config('database.default'),
                'DB_DATABASE' => DB::getDatabaseName(),
            ],
        );
        $process->setTimeout(60);
        $process->start();

        return $process;
    });

    return $processes->map(function (Process $process) {
        $process->wait();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());

        return $process->getOutput();
    })->all();
}

it('lets exactly one of eight simultaneous requests take the last item', function () {
    $listing = Listing::factory()->create(['quantity_total' => 1, 'quantity_available' => 1]);

    $results = collect(raceToReserve($listing, customers: 8, quantityEach: 1));

    expect($results->filter(fn (string $r) => str_starts_with($r, 'RESERVED:')))->toHaveCount(1)
        ->and($results->filter(fn (string $r) => $r === 'REFUSED:sold_out'))->toHaveCount(7)
        ->and(Reservation::count())->toBe(1)
        ->and($listing->fresh()->quantity_available)->toBe(0)
        ->and($listing->fresh()->status)->toBe(ListingStatus::SoldOut);
});

it('never sells more than the stock when many reserve at once', function () {
    $listing = Listing::factory()->create(['quantity_total' => 5, 'quantity_available' => 5]);

    $results = collect(raceToReserve($listing, customers: 8, quantityEach: 2));

    $won = $results->filter(fn (string $r) => str_starts_with($r, 'RESERVED:'));

    // 5 in stock, 2 each: two customers get theirs, 1 portion stays, six are refused.
    expect($won)->toHaveCount(2)
        ->and($results->filter(fn (string $r) => $r === 'REFUSED:not_enough_stock'))->toHaveCount(6)
        ->and((int) Reservation::sum('quantity'))->toBe(4)
        ->and($listing->fresh()->quantity_available)->toBe(1)
        ->and($listing->fresh()->status)->toBe(ListingStatus::Live)
        // Pickup codes at one shop never clash.
        ->and($won->unique())->toHaveCount(2)
        ->and(Reservation::pluck('pickup_code')->unique())->toHaveCount(2);
});
