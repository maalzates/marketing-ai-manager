<?php

declare(strict_types=1);

namespace App\Modules\Chat\Infrastructure\Persistence;

use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'user_id',
        'title',
        'summary',
        'last_message_at',
    ];

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    protected static function newFactory(): Factory
    {
        return ChatConversationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }
}
