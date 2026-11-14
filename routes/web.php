<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\DealController;
use App\Http\Controllers\DevPreviewController;
use App\Http\Controllers\ImpactController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\Seller;
use Illuminate\Support\Facades\Route;

/*
| Who can open what:
|
|   everyone ............ /  (the deals), /listings/{id}, /login, /register
|   guests .............. /sell (shop sign-up)
|   customers only ...... reserving, /orders/...
|   sellers only ........ /seller/...
|   admins only ......... /admin/...
|
| `auth` sends guests to /login; `role:x` gives everyone else a 403.
| On top of this, the policies in app/Policies decide what a signed-in person
| may do with one particular listing or reservation (only their own).
|
| The login, register and logout routes come from Laravel Fortify
| (app/Providers/FortifyServiceProvider.php).
*/

Route::get('/', [DealController::class, 'index'])->name('deals.index');
Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::post('/listings/{listing}/reservations', [ReservationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('reservations.store');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{reservation}', [OrderController::class, 'show'])->name('orders.show');
    Route::delete('/orders/{reservation}', [OrderController::class, 'destroy'])->name('orders.destroy');

    Route::get('/impact', ImpactController::class)->name('impact');

    Route::post('/listings/{listing}/reports', [ReportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('reports.store');
});

// "Sell on FoodLoop": a shop's own sign-up page (signed-in people get sent home).
Route::middleware('guest')->group(function () {
    Route::get('/sell', [Seller\SignUpController::class, 'create'])->name('seller.sign-up');
    Route::post('/sell', [Seller\SignUpController::class, 'store'])->middleware('throttle:10,1')->name('seller.sign-up.store');
});

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/', Seller\DashboardController::class)->name('dashboard');

    Route::get('/listings', [Seller\ListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/new', [Seller\ListingController::class, 'create'])->name('listings.create');
    Route::post('/listings', [Seller\ListingController::class, 'store'])->name('listings.store');
    Route::get('/listings/{listing}/edit', [Seller\ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/listings/{listing}', [Seller\ListingController::class, 'update'])->name('listings.update');
    Route::post('/listings/{listing}/publish', [Seller\ListingController::class, 'publish'])->name('listings.publish');
    Route::post('/listings/{listing}/pause', [Seller\ListingController::class, 'pause'])->name('listings.pause');
    Route::delete('/listings/{listing}', [Seller\ListingController::class, 'destroy'])->name('listings.destroy');
    Route::post('/listings/{listing}/donation', [Seller\ListingController::class, 'offerLeftovers'])->name('listings.donation');

    Route::get('/pickups', [Seller\PickupController::class, 'index'])->name('pickups.index');
    Route::post('/pickups/collect', [Seller\PickupController::class, 'collect'])->middleware('throttle:30,1')->name('pickups.collect');

    Route::get('/donations', Seller\DonationController::class)->name('donations.index');

    Route::get('/profile', [Seller\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Seller\ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\OverviewController::class)->name('dashboard');

    Route::get('/sellers', [Admin\SellerController::class, 'index'])->name('sellers.index');
    Route::post('/sellers/{seller}/approve', [Admin\SellerController::class, 'approve'])->name('sellers.approve');
    Route::post('/sellers/{seller}/reject', [Admin\SellerController::class, 'reject'])->name('sellers.reject');
    Route::post('/sellers/{seller}/suspend', [Admin\SellerController::class, 'suspend'])->name('sellers.suspend');
    Route::get('/sellers/{seller}/certificate', [Admin\SellerController::class, 'certificate'])->name('sellers.certificate');

    // Reports are handled per listing: one button deals with every open report on it.
    Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/{listing}/hide', [Admin\ReportController::class, 'hide'])->name('reports.hide');
    Route::post('/reports/{listing}/warn', [Admin\ReportController::class, 'warn'])->name('reports.warn');
    Route::post('/reports/{listing}/dismiss', [Admin\ReportController::class, 'dismiss'])->name('reports.dismiss');

    Route::get('/customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers/{user}/lift-limit', [Admin\CustomerController::class, 'liftLimit'])->name('customers.lift-limit');
});

/*
| Developer previews of the design system. Only when APP_ENV=local; anywhere
| else these addresses answer 404.
*/
Route::middleware('local')->prefix('dev')->name('dev.')->group(function () {
    Route::get('/components', [DevPreviewController::class, 'components'])->name('components');
    Route::get('/layouts/customer', [DevPreviewController::class, 'customerLayout'])->name('layouts.customer');
    Route::get('/layouts/dashboard', [DevPreviewController::class, 'dashboardLayout'])->name('layouts.dashboard');
});
