<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Restricted, not cascaded: the verdict history under a strategy is the memory of
            // the whole system, and a DELETE on the parent must not be able to erase it.
            $table->foreignId('strategy_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->enum('type', ['organic', 'ads']);
            $table->enum('platform', ['instagram', 'facebook', 'youtube', 'tiktok']);
            $table->string('title', 191);
            $table->text('hypothesis');
            $table->json('expected_result');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->decimal('max_budget', 12, 2)->nullable();
            $table->json('configuration');
            $table->enum('status', ['draft', 'scheduled', 'running', 'completed', 'cancelled'])->default('draft');
            $table->enum('production_status', ['script', 'recorded', 'scheduled', 'published', 'failed'])->nullable();
            $table->decimal('spend_total', 12, 2)->default(0);
            $table->timestamp('learning_phase_ends_at')->nullable();
            $table->enum('verdict', ['worked', 'did_not_work', 'inconclusive'])->nullable();
            $table->text('verdict_reason')->nullable();
            $table->timestamp('verdict_confirmed_at')->nullable();
            $table->boolean('closed_early')->default(false);
            $table->timestamps();

            $table->unique(['account_id', 'code']);
            $table->index(['account_id', 'strategy_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};
