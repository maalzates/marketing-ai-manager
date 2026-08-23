<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

readonly class CreateApiKeyDTO
{
    /** @param list<string> $abilities */
    public function __construct(
        public ?int $accountId,
        public string $name,
        public array $abilities,
        public int $createdByUserId,
    ) {}
}
