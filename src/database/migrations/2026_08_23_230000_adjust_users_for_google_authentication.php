<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['password', 'email_verified_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_url')->nullable()->after('email');
            $table->string('google_id')->nullable()->unique()->after('avatar_url');
            $table->string('timezone')->default('UTC')->after('google_id');
            $table->string('locale')->default('es')->after('timezone');
            $table->boolean('is_active')->default(true)->after('locale');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['avatar_url', 'google_id', 'timezone', 'locale', 'is_active', 'last_login_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            // Defaulted because the rows that exist at rollback time have no password to restore.
            $table->string('password')->default('')->after('email_verified_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
