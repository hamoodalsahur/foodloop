<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire')]
#[Description('Mark uncollected reservations as no-shows and release blocked customers\' reservations (runs every minute)')]
class ExpireReservations extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireOverdue();

        $this->info("Marked {$count} reservation(s) as no-show.");

        if (($released = $reservations->releaseBlockedCustomers()) > 0) {
            $this->info("Cancelled {$released} waiting reservation(s) of blocked customers.");
        }

        return self::SUCCESS;
    }
}
