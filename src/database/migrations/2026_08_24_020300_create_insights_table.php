<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('competitor_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('kind', ['pattern', 'content_idea', 'sentiment']);
            $table->string('title', 191);
            $table->text('body');
            $table->json('evidence');
            $table->decimal('score', 6, 3)->default(0);
            $table->enum('source', ['competitor_analysis', 'comment_mining', 'own_content']);
            $table->enum('status', ['new', 'used', 'discarded'])->default('new');
            $table->timestamps();

            $table->index(['account_id', 'kind', 'status']);
            $table->index(['account_id', 'competitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
    }
};
