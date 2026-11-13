<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't need the built CSS/JS, so they pass without `npm run build`.
        $this->withoutVite();
    }
}
