<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\DTO;

use App\Modules\Chat\Domain\Enums\MessageRole;

readonly class CreateChatMessageDTO
{
    /**
     * @param  array<string, mixed>|null  $toolInput
     * @param  array<string, mixed>|null  $toolResult
     */
    public function __construct(
        public int $accountId,
        public int $conversationId,
        public MessageRole $role,
        public ?string $content = null,
        public ?string $toolName = null,
        public ?string $toolUseId = null,
        public ?array $toolInput = null,
        public ?array $toolResult = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}
}
