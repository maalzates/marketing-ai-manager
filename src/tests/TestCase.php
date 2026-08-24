<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The PHP suite must not depend on a Vite build: CI runs it without ever
        // compiling assets, so `@vite` would fail on a missing manifest.
        $this->withoutVite();
    }
}
