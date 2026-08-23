<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            // Restricted, not cascaded: the brand profile is the root context of every
            // strategy below it, so losing it silently would orphan their whole meaning.
            $table->foreignId('brand_profile_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->text('objective');
            $table->string('north_star_metric');
            $table->decimal('monthly_budget', 12, 2)->nullable();
            $table->json('constraints');
            $table->json('guardian_config');
            $table->json('organic_cadence');
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategies');
    }
};
