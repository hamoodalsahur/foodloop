<?php

use App\Http\Controllers\DealController;
use App\Http\Controllers\DevPreviewController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

/*
| Who can open what:
|
|   everyone ............ /  (the deals), /listings/{id}, /login, /register
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
});

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::view('/', 'areas.seller')->name('dashboard');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'areas.admin')->name('dashboard');
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
