<?php

use App\Http\Controllers\DevPreviewController;
use Illuminate\Support\Facades\Route;

/*
| Who can open what:
|
|   everyone ............ /                (later: deals, listing pages)
|   customers only ...... /orders          (later: impact, reservations)
|   sellers only ........ /seller/...
|   admins only ......... /admin/...
|
| `auth` sends guests to /login; `role:x` gives everyone else a 403.
| On top of this, the policies in app/Policies decide what a signed-in person
| may do with one particular listing or reservation (only their own).
*/

Route::view('/', 'home')->name('home');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::view('/orders', 'areas.orders')->name('orders.index');
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
