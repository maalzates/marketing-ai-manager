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

        // Nor on whoever runs it holding real Google credentials. An empty client id makes
        // the authorisation URL refuse to build, which passes on a developer's machine and
        // fails on CI, where .env.example leaves these blank.
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
        ]);
    }
}
