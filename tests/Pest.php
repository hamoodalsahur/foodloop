<?php

use App\Enums\RefusalReason;
use App\Exceptions\ReservationRefused;
use App\Models\Listing;
use App\Models\Seller;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Every test in tests/Feature boots the app and starts from a freshly
| migrated, empty database (SQLite in memory by default; MySQL when run
| with phpunit.mysql.xml, see README "Run the tests").
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
| tests/Concurrency starts real, separate PHP processes that race each
| other, so it manages the database itself and only runs on MySQL.
*/

pest()->extend(TestCase::class)->in('Concurrency');

/** The reservation engine, as the app would resolve it. */
function reservations(): ReservationService
{
    return app(ReservationService::class);
}

/** Run $attempt and expect the reservation engine to refuse it for $reason. */
function expectRefusal(Closure $attempt, RefusalReason $reason): void
{
    try {
        $attempt();
    } catch (ReservationRefused $refused) {
        expect($refused->reason)->toBe($reason)
            ->and($refused->getMessage())->toBe($reason->label());

        return;
    }

    test()->fail("Expected a refusal ({$reason->value}) but it went through.");
}

/**
 * A live listing from an approved shop that sits $km north of the demo
 * "you are here" point, for tests of the deals page.
 *
 * @param  array<string, mixed>  $attributes
 */
function dealAt(float $km, array $attributes = [], string $shop = 'Test Shop'): Listing
{
    $origin = config('foodloop.demo_origin');

    $seller = Seller::factory()->approved()->create([
        'business_name' => $shop,
        'lat' => $origin['lat'] + $km * 0.009,
        'lng' => $origin['lng'],
    ]);

    return Listing::factory()->for($seller)->create($attributes);
}

/**
 * The one message of this kind in someone's inbox (see App\Services\Notifier
 * for the kinds), as ['kind', 'title', 'body', 'url'], or null if there is none.
 *
 * @return array{kind: string, title: string, body: string, url: string|null}|null
 */
function messageFor(User $user, string $kind): ?array
{
    $matches = $user->notifications()->get()->where('data.kind', $kind);

    if ($matches->count() > 1) {
        test()->fail("Expected at most one \"{$kind}\" message but found {$matches->count()}.");
    }

    return $matches->first()?->data;
}

/**
 * Every kind of message in someone's inbox, sorted, e.g. ['reservation.booked'].
 *
 * @return list<string>
 */
function messageKinds(User $user): array
{
    return $user->notifications()->get()->pluck('data.kind')->sort()->values()->all();
}
