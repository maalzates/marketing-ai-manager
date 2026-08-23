<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Application\DTO;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;

readonly class KnowledgeFilterDTO
{
    public function __construct(
        public ?KnowledgeType $type,
        public ?string $key,
        public ?string $locale,
        public ?bool $isPublished,
        public int $perPage,
        public int $page,
    ) {}
}
