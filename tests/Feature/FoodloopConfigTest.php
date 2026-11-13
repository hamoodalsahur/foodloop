<?php

// The business rules from docs/BUILD_PLAN.md §3. If a rule changes, change
// config/foodloop.php and this test together.

it('keeps the business rule values in config/foodloop.php', function () {
    expect(config('foodloop.max_qty_per_reservation'))->toBe(3)
        ->and(config('foodloop.free_cancel_minutes'))->toBe(30)
        ->and(config('foodloop.grace_minutes'))->toBe(15)
        ->and(config('foodloop.strikes_to_block'))->toBe(3)
        ->and(config('foodloop.block_days'))->toBe(7);
});

it('lists the allowed halal statuses and payment methods', function () {
    expect(config('foodloop.halal_statuses'))->toBe(['certified', 'muslim_owned', 'not_halal'])
        ->and(config('foodloop.payment_methods'))->toBe(['pay_now', 'pay_at_pickup']);
});

it('runs on Kuala Lumpur time', function () {
    expect(config('app.timezone'))->toBe('Asia/Kuala_Lumpur')
        ->and(now()->timezoneName)->toBe('Asia/Kuala_Lumpur');
});
