<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analysis_cache', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 64);
            $table->char('input_hash', 64);
            $table->json('result');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['account_id', 'kind', 'input_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analysis_cache');
    }
};
