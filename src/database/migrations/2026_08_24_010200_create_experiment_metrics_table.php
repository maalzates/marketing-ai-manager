<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiment_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('cpm', 10, 4)->default(0);
            $table->decimal('cpc', 10, 4)->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('cpa', 12, 4)->nullable();
            $table->decimal('frequency', 8, 4)->nullable();
            $table->unsignedBigInteger('video_views')->default(0);
            $table->unsignedBigInteger('engagement')->default(0);
            $table->json('raw');
            $table->timestamps();

            $table->unique(['experiment_id', 'date']);
            $table->index(['account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_metrics');
    }
};
