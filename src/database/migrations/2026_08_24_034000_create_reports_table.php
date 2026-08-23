<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', ['experiment_verdict', 'periodic']);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->longText('body');
            $table->json('data');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['account_id', 'type', 'generated_at']);
            // The two reads that decide whether a report already exists, and therefore whether
            // the daily jobs pay for another LLM call. Both run before anything is generated.
            $table->index(['experiment_id', 'type']);
            $table->index(['strategy_id', 'type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
