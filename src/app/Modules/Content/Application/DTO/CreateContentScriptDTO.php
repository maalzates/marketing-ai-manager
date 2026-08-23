<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ContentFormat;

readonly class CreateContentScriptDTO
{
    /**
     * @param  list<array{beat: string, detail: string}>  $structure
     * @param  list<array{type: string, aspect_ratio: string|null, duration_seconds: int|null, quantity: int}>  $requiredAssets
     * @param  list<int>  $sourceInsightIds
     */
    public function __construct(
        public int $accountId,
        public int $strategyId,
        public string $title,
        public string $hook,
        public array $structure,
        public string $cta,
        public ContentFormat $format,
        public array $requiredAssets,
        public array $sourceInsightIds = [],
    ) {}
}
