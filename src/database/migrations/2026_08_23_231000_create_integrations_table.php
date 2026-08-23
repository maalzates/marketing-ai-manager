<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['anthropic', 'openai', 'gemini', 'apify', 'meta', 'google', 'youtube', 'tiktok']);
            $table->enum('kind', ['api_key', 'oauth']);
            $table->text('credentials');
            $table->enum('status', ['connected', 'disconnected', 'error', 'expired'])->default('disconnected');
            $table->string('external_account_id')->nullable();
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
