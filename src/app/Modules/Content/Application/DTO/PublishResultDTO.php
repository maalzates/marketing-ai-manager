<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

readonly class PublishResultDTO
{
    public function __construct(
        public string $externalPostId,
        public ?string $permalink = null,
        public array $raw = [],
    ) {}
}
