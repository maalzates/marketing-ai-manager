<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiment_warnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->text('message');
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->timestamp('applies_from')->nullable();
            $table->timestamp('applies_to')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['account_id', 'experiment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_warnings');
    }
};
