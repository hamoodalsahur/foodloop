<?php

use Illuminate\Support\Facades\Schedule;

/*
| The reservation engine's clockwork. Both commands run every minute and
| are safe to run again and again. Locally, keep them running with:
|
|     php artisan schedule:work
*/

// 1. Reservations not collected by the end of the grace period become no-shows.
Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();

// 2. Listings past their pickup window close; leftovers are offered to the food bank.
Schedule::command('listings:close')->everyMinute()->withoutOverlapping();
