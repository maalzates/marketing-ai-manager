<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant', 'tool']);
            $table->longText('content')->nullable();
            $table->string('tool_name')->nullable();
            $table->string('tool_use_id')->nullable();
            $table->json('tool_input')->nullable();
            $table->json('tool_result')->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['chat_conversation_id', 'id']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
