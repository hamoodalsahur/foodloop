<?php

use App\Enums\RefusalReason;
use App\Exceptions\ReservationRefused;
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
