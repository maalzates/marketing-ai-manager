<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\DTO;

readonly class StartConversationDTO
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public ?string $title = null,
    ) {}
}
