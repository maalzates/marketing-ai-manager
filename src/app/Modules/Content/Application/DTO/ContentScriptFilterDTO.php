<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ContentFormat;
use App\Modules\Content\Domain\Enums\ScriptStatus;

readonly class ContentScriptFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $strategyId,
        public ?ScriptStatus $status,
        public ?ContentFormat $format,
        public int $perPage,
        public int $page,
    ) {}
}
