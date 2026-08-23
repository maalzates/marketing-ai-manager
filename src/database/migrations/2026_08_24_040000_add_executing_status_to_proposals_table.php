<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A proposal whose mutation runs on the queue needs a state between `accepted` and
 * `executed`: reporting `executed` before the platform has confirmed anything tells the
 * user a campaign exists when it may still fail.
 */
return new class extends Migration
{
    private const string WITH_EXECUTING = "ENUM('pending','accepted','executing','executed','rejected','failed','expired')";

    private const string WITHOUT_EXECUTING = "ENUM('pending','accepted','executed','rejected','failed','expired')";

    public function up(): void
    {
        DB::statement('ALTER TABLE proposals MODIFY status '.self::WITH_EXECUTING." NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('proposals')->where('status', 'executing')->update(['status' => 'accepted']);

        DB::statement('ALTER TABLE proposals MODIFY status '.self::WITHOUT_EXECUTING." NOT NULL DEFAULT 'pending'");
    }
};
