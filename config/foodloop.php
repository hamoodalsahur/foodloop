<?php

/*
|--------------------------------------------------------------------------
| FoodLoop business rules
|--------------------------------------------------------------------------
|
| Every number the reservation rules depend on lives here, so changing a
| rule is a one-line edit. See docs/BUILD_PLAN.md §3.
|
| ⚠️ These values were proposed by Claude and still need the team's
| confirmation. Read them in code with config('foodloop.grace_minutes') etc.
| Never hard-code these numbers anywhere else.
|
*/

return [

    // The most portions one customer can hold of one listing, counted across
    // all their reservations on it that are not cancelled.
    'max_qty_per_listing' => 3,

    // A reservation can be cancelled for free until this many minutes before
    // the pickup window starts. Cancelling later counts as a no-show strike.
    'free_cancel_minutes' => 30,

    // "Changed my mind": cancelling within this many minutes of reserving is
    // always free, even after the cutoff above.
    'change_of_mind_minutes' => 5,

    // Minutes after the pickup window ends before an uncollected reservation
    // becomes a no-show.
    'grace_minutes' => 15,

    // No-show strikes before a customer is blocked from reserving.
    'strikes_to_block' => 3,

    // How many days a block lasts.
    'block_days' => 7,

    // Allowed halal statuses for a listing (required on every listing).
    // 'certified' is only allowed when the seller has an approved halal
    // certificate on file.
    'halal_statuses' => ['certified', 'muslim_owned', 'not_halal'],

    // Payments are simulated only (no gateway).
    'payment_methods' => ['pay_now', 'pay_at_pickup'],

];
