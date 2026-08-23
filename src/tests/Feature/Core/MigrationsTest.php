<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Proves the suite really runs against MySQL. If this ever passes on sqlite, the
 * repository tests stopped testing the queries that ship.
 */
class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_suite_runs_against_the_mysql_testing_schema(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());

        // A suffix is allowed so concurrent suites can each own a schema; the prefix is
        // what proves nobody pointed the suite at a scratch database — or at sqlite.
        $this->assertStringStartsWith('marketing_ai_testing', DB::connection()->getDatabaseName());
    }

    public function test_the_migrations_build_the_schema(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('jobs'));
    }
}
