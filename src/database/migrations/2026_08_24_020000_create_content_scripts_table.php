<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_scripts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 191);
            $table->text('hook');
            $table->json('structure');
            $table->text('cta');
            $table->enum('format', ['reel', 'carousel', 'story', 'photo', 'video']);
            $table->json('required_assets');
            $table->json('source_insight_ids');
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->timestamps();

            $table->index(['account_id', 'strategy_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_scripts');
    }
};
