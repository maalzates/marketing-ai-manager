<?php

declare(strict_types=1);

namespace App\Modules\Chat\Infrastructure\Persistence;

use App\Modules\Chat\Domain\Enums\MessageRole;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'chat_conversation_id',
        'role',
        'content',
        'tool_name',
        'tool_use_id',
        'tool_input',
        'tool_result',
        'input_tokens',
        'output_tokens',
    ];

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    protected static function newFactory(): Factory
    {
        return ChatMessageFactory::new();
    }

    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'tool_input' => 'array',
            'tool_result' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }
}
