<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'create_campaign',
                'budget_change',
                'pause_experiment',
                'close_experiment',
                'schedule_content',
            ]);
            $table->string('title', 191);
            $table->text('rationale');
            $table->json('payload');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'executed', 'failed', 'expired'])
                ->default('pending');
            $table->enum('origin', ['chat', 'guardian', 'evaluation', 'planner']);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('execution_result')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
