<?php

use App\Support\CustomerName;

it('shortens a name to first name and initial', function (string $name, string $short) {
    expect(CustomerName::short($name))->toBe($short);
})->with([
    ['Aina Rahman', 'Aina R.'],
    ['Muhammad Hafiz bin Ismail', 'Muhammad H.'],
    ['Daniel', 'Daniel'],
    ['  siti   aminah ', 'siti A.'],
]);
