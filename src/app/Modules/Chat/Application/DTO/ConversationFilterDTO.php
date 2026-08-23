<?php

declare(strict_types=1);

namespace App\Modules\Chat\Application\DTO;

readonly class ConversationFilterDTO
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public int $perPage,
        public int $page,
    ) {}
}
