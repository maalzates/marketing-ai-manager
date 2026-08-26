<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three glossary keys were hyphenated while every key the interface asks for uses
 * underscores. The seeder upserts by key, so renaming them left the old rows behind and the
 * glossary listed each of the three twice.
 */
return new class extends Migration
{
    private const array SUPERSEDED = ['hook-rate', 'engagement-rate', 'cost-per-follower'];

    public function up(): void
    {
        DB::table('knowledge_entries')
            ->where('type', 'glossary_term')
            ->whereIn('key', self::SUPERSEDED)
            ->delete();
    }

    /** The seeder is the source of these rows: re-running it restores whatever it declares. */
    public function down(): void {}
};
