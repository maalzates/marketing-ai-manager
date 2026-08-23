<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('platform', ['instagram', 'facebook', 'youtube', 'tiktok']);
            $table->timestamp('scheduled_at');
            $table->timestamp('published_at')->nullable();
            $table->enum('status', ['pending', 'publishing', 'published', 'failed'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('external_post_id')->nullable()->index();
            $table->timestamps();

            $table->index(['account_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_schedules');
    }
};
