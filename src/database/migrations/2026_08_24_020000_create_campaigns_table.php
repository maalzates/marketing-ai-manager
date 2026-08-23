<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->string('external_campaign_id')->nullable();
            $table->string('external_adset_id')->nullable();
            $table->string('external_ad_id')->nullable();
            $table->enum('objective', [
                'OUTCOME_AWARENESS',
                'OUTCOME_TRAFFIC',
                'OUTCOME_ENGAGEMENT',
                'OUTCOME_LEADS',
                'OUTCOME_APP_PROMOTION',
                'OUTCOME_SALES',
            ]);
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->json('targeting');
            $table->enum('status', ['draft', 'launching', 'paused', 'active', 'failed', 'archived'])->default('draft');
            $table->boolean('advantage_plus_creative')->default(false);
            $table->boolean('sandbox')->default(false);
            $table->enum('learning_stage', ['LEARNING', 'SUCCESS', 'FAIL'])->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // One campaign per experiment: the whole module addresses campaigns by experiment,
            // and it is what makes a resumed launch update the same row instead of duplicating it.
            $table->unique(['account_id', 'experiment_id']);
            $table->index('external_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
