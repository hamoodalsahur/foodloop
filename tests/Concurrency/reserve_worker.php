<?php

/*
| One "customer" in the race test (see RaceForStockTest.php). It boots the
| app in its own PHP process, waits for the starting gun, tries to reserve,
| and prints what happened.
|
| Usage: php reserve_worker.php <listing id> <customer id> <quantity> <start at, unix time>
*/

use App\Enums\PaymentMethod;
use App\Exceptions\ReservationRefused;
use App\Models\Listing;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $listingId, $customerId, $quantity, $startAt] = $argv;

// Everyone loads the listing while it still shows stock…
$listing = Listing::findOrFail($listingId);
$customer = User::findOrFail($customerId);

// …then all go at the same moment.
while (microtime(true) < (float) $startAt) {
    usleep(1000);
}

try {
    $reservation = $app->make(ReservationService::class)
        ->reserve($customer, $listing, (int) $quantity, PaymentMethod::PayNow);

    echo 'RESERVED:'.$reservation->pickup_code;
} catch (ReservationRefused $refused) {
    echo 'REFUSED:'.$refused->reason->value;
}
