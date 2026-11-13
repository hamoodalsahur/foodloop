<?php

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
