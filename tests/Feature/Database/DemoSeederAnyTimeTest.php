<?php

// The demo data is built relative to "now", so it has to look right whenever
// it is seeded, including the awkward edges of a week.

use App\Enums\ListingStatus;
use App\Enums\ReservationStatus;
use App\Models\Listing;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

it('builds sensible demo data whenever it is seeded', function (string $moment) {
    Storage::fake('public');
    Storage::fake('local');

    $this->travelTo($moment);
    $this->seed(DatabaseSeeder::class);

    $ainasPickups = User::firstWhere('email', 'aina@foodloop.test')
        ->reservations()
        ->where('status', ReservationStatus::Collected)
        ->get();

    $perWeek = $ainasPickups
        ->groupBy(fn (Reservation $r) => (int) $r->collected_at->startOfWeek()->diffInWeeks(now()->startOfWeek()))
        ->map->sum('quantity')
        ->sortKeysDesc()
        ->values()
        ->all();

    expect($perWeek)->toBe([1, 2, 4, 2, 3, 2])
        ->and($ainasPickups->every(fn (Reservation $r) => $r->collected_at->lessThanOrEqualTo(now())))->toBeTrue()
        ->and(Listing::live()->count())->toBe(5)
        ->and(Listing::live()->get()->every(fn (Listing $l) => $l->pickup_end->isFuture()))->toBeTrue()
        ->and(Listing::where('status', ListingStatus::Closed)->get()
            ->every(fn (Listing $l) => $l->pickup_end->lessThanOrEqualTo(now())))->toBeTrue();
})->with([
    'Monday just after midnight' => ['2026-11-16 00:05:00'],
    'Monday morning' => ['2026-11-16 09:00:00'],
    'Wednesday evening' => ['2026-11-18 18:18:00'],
    'Sunday just before midnight' => ['2026-11-22 23:55:00'],
    'New Year\'s Day' => ['2027-01-01 12:00:00'],
]);
