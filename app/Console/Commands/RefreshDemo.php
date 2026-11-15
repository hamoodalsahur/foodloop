<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\User;
use App\Support\Demo\DemoDeals;
use App\Support\Demo\DemoRefresh;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('demo:refresh
    {--menu= : "random" (a different pick each time) or "mockup" (the six deals on the mockups). Default: DEMO_MENU in .env, else random}
    {--seed= : A number. The same number gives the same random pick}')]
#[Description('DEMO DATA: post a fresh set of deals closing soon. Keeps every account and all past orders (migrate:fresh --seed is the full wipe)')]
class RefreshDemo extends Command
{
    public function handle(DemoRefresh $refresh): int
    {
        if (app()->isProduction()) {
            $this->error('demo:refresh posts made-up deals. It must not run in production.');

            return self::FAILURE;
        }

        if (($missing = DemoDeals::missing()) !== []) {
            $this->warn('The demo data isn\'t (all) there, so there is nothing to refresh. Missing:');
            collect($missing)->each(fn (string $what) => $this->line('  - '.$what));
            $this->warn('Nothing was changed. To set up the demo from scratch run: php artisan migrate:fresh --seed');

            return self::FAILURE;
        }

        $seed = $this->option('seed');

        if ($seed !== null && ! ctype_digit((string) $seed)) {
            $this->error('--seed must be a whole number, for example --seed=7');

            return self::FAILURE;
        }

        try {
            $deals = new DemoDeals($this->option('menu'), $seed === null ? null : (int) $seed);
        } catch (InvalidArgumentException $problem) {
            $this->error($problem->getMessage());

            return self::FAILURE;
        }

        $done = $refresh->run($deals);

        $this->info("Demo deals refreshed: {$done['posted']} fresh listings posted, {$done['closed']} old ones closed, "
            ."{$done['removed']} waiting reservation(s) removed.");
        $this->line('Kept: '.User::count().' accounts and '.(Reservation::count()).' orders (past orders plus tonight\'s demo reservations).');
        $this->line('Aina\'s codes for the demo script: 4721 (Bakery Surprise Bundle) and 2468 (Sandwich & pastry pack, pickup open now).');

        return self::SUCCESS;
    }
}
