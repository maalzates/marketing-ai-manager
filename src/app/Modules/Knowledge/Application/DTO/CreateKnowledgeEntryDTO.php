<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Application\DTO;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;

readonly class CreateKnowledgeEntryDTO
{
    public function __construct(
        public KnowledgeType $type,
        public string $key,
        public string $locale,
        public string $title,
        public string $body,
        public array $metadata,
        public bool $isPublished,
    ) {}
}
