<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `preferences.currency` was a second currency that nothing read: budgets have always been
 * converted with `accounts.currency`. Dropping the key from the registry without moving the
 * value would silently revert every account that had chosen one.
 */
return new class extends Migration
{
    private const string KEY = 'preferences.currency';

    public function up(): void
    {
        DB::table('settings')
            ->where('scope', 'account')
            ->where('key', self::KEY)
            ->whereNotNull('scope_id')
            ->get(['scope_id', 'value'])
            ->each(function (object $setting): void {
                $currency = json_decode((string) $setting->value, true);

                if (! is_string($currency) || strlen($currency) !== 3) {
                    return;
                }

                DB::table('accounts')
                    ->where('id', $setting->scope_id)
                    ->update(['currency' => strtoupper($currency)]);
            });

        DB::table('settings')->where('key', self::KEY)->delete();
    }

    /** The key no longer exists in the registry, so writing it back would be unreachable data. */
    public function down(): void {}
};
