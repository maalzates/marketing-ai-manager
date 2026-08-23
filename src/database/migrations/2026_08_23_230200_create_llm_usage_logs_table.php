<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 191);
            $table->string('provider', 64);
            $table->string('model', 191);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedInteger('cached_input_tokens')->default(0);
            $table->decimal('estimated_cost_usd', 12, 6)->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'created_at']);
            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_usage_logs');
    }
};
