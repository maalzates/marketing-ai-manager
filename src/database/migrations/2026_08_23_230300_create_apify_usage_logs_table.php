<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apify_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('actor_id', 191);
            $table->string('run_id', 191)->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apify_usage_logs');
    }
};
