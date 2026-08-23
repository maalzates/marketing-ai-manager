<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\DTO;

readonly class SendChatMessageDTO
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public ?int $conversationId,
        public string $message,
    ) {}
}
