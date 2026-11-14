<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate:fresh --seed` fills the app with the demo data
     * from the mockups. Model events stay on, so the demo data has to follow
     * the same business rules as everything else.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
