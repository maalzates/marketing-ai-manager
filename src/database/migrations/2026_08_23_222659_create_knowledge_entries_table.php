<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_entries', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['domain_rule', 'glossary_term', 'onboarding_guide', 'prompt_template']);
            $table->string('key', 191);
            $table->string('locale', 5)->default('es');
            $table->string('title');
            $table->longText('body');
            $table->json('metadata');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['type', 'key', 'locale', 'version']);
            $table->index(['type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }
};
