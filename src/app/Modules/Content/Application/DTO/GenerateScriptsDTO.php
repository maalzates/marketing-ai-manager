<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ContentFormat;

readonly class GenerateScriptsDTO
{
    /** @param  list<ContentFormat>  $formats */
    public function __construct(
        public int $accountId,
        public int $strategyId,
        public int $count,
        public array $formats = [],
        public ?string $brief = null,
        public ?int $userId = null,
    ) {}
}
