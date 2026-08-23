<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;

readonly class UpdateContentScriptDTO
{
    /**
     * @param  list<array{beat: string, detail: string}>|null  $structure
     * @param  list<array{type: string, aspect_ratio: string|null, duration_seconds: int|null, quantity: int}>|null  $requiredAssets
     */
    public function __construct(
        public int $accountId,
        public int $scriptId,
        public ?string $title,
        public ?string $hook,
        public ?array $structure,
        public ?string $cta,
        public ?ContentFormat $format,
        public ?array $requiredAssets,
        public ?ScriptStatus $status,
    ) {}
}
