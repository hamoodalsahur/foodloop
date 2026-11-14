<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reservations:expire')]
#[Description('Mark reservations that were not collected in time as no-shows (runs every minute)')]
class ExpireReservations extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireOverdue();

        $this->info("Marked {$count} reservation(s) as no-show.");

        return self::SUCCESS;
    }
}
