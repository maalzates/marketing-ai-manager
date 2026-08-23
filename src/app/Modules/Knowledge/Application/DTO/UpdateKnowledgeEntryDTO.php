<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Application\DTO;

readonly class UpdateKnowledgeEntryDTO
{
    public function __construct(
        public int $id,
        public ?string $title,
        public ?string $body,
        public ?array $metadata,
        public ?bool $isPublished,
    ) {}
}
