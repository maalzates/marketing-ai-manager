<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['personal_brand', 'company', 'project']);
            $table->text('description');
            $table->string('niche')->nullable();
            $table->text('value_proposition')->nullable();
            $table->text('tone_of_voice')->nullable();
            $table->json('values');
            $table->json('banned_topics');
            $table->json('buyer_personas');
            $table->json('reference_competitors');
            $table->json('brand_colors');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_profiles');
    }
};
