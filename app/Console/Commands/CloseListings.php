<?php

namespace App\Console\Commands;

use App\Services\ListingCloser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('listings:close')]
#[Description('Close listings whose pickup window has ended and offer leftovers to the food bank (runs every minute)')]
class CloseListings extends Command
{
    public function handle(ListingCloser $closer): int
    {
        $closed = $closer->closeEnded();
        $offered = $closer->offerLeftovers();

        $this->info("Closed {$closed} listing(s). Offered leftovers from {$offered} listing(s) to the food bank.");

        return self::SUCCESS;
    }
}
