<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instagram removed `follower_count` from insights, so the follower time series can no
     * longer be read back from the API — it only exists if we snapshot it ourselves.
     */
    public function up(): void
    {
        Schema::create('channel_audience_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('platform', ['instagram', 'facebook', 'youtube', 'tiktok']);
            $table->date('date');
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('follows_count')->default(0);
            $table->unsignedBigInteger('media_count')->default(0);
            $table->json('raw');
            $table->timestamps();

            $table->unique(['account_id', 'platform', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_audience_snapshots');
    }
};
