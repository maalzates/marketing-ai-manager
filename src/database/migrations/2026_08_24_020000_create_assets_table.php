<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('experiment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_asset_id')->nullable()->constrained('assets')->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('name', 191);
            $table->enum('type', ['photo', 'video_vertical', 'reel', 'carousel', 'carousel_slide', 'story']);
            $table->string('aspect_ratio', 16)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->enum('status', ['draft', 'ready', 'used', 'archived', 'broken'])->default('draft');
            $table->string('meta_asset_id')->nullable();
            $table->enum('meta_asset_type', ['image_hash', 'video_id'])->nullable();
            $table->json('spec_warnings');
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'experiment_id']);
            $table->index(['parent_asset_id', 'position']);
            $table->index('drive_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
