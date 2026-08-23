<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('url', 512);
            $table->string('type', 32);
            $table->text('caption')->nullable();
            $table->timestamp('posted_at')->nullable();
            // Nullable, not 0: Instagram reports -1 when the profile hides its like count,
            // and averaging that as zero understates every engagement figure downstream.
            $table->unsignedBigInteger('likes')->nullable();
            $table->unsignedBigInteger('comments_count')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->decimal('engagement_rate', 8, 4)->nullable();
            $table->enum('sentiment', ['positive', 'negative', 'neutral'])->nullable();
            $table->json('sentiment_summary')->nullable();
            $table->json('raw');
            $table->timestamps();

            $table->unique(['competitor_id', 'external_id']);
            $table->index(['account_id', 'competitor_id', 'posted_at']);
            $table->index(['account_id', 'competitor_id', 'sentiment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_posts');
    }
};
