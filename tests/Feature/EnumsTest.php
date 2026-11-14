<?php

use App\Enums\HalalStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;

it('keeps the halal statuses and payment methods in step with config/foodloop.php', function () {
    expect(HalalStatus::values())->toBe(config('foodloop.halal_statuses'))
        ->and(PaymentMethod::values())->toBe(config('foodloop.payment_methods'));
});

it('has exactly the four reservation states from the lifecycle', function () {
    expect(ReservationStatus::values())->toBe(['reserved', 'collected', 'cancelled', 'no_show']);
});

it('has the three roles', function () {
    expect(UserRole::values())->toBe(['customer', 'seller', 'admin']);
});

it('gives every enum case a label for people to read', function () {
    foreach (glob(app_path('Enums/*.php')) as $file) {
        $enum = 'App\\Enums\\'.basename($file, '.php');

        foreach ($enum::cases() as $case) {
            expect($case->label())->toBeString()->not->toBeEmpty();
        }
    }
});
