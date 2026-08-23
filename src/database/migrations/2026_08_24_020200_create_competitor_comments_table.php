<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_post_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('author', 191)->nullable();
            $table->text('text');
            $table->unsignedBigInteger('likes')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['competitor_post_id', 'external_id']);
            $table->index(['account_id', 'competitor_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_comments');
    }
};
