<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

readonly class RecordingBatchDTO
{
    /** @param  array<int, int>  $assetIdByScriptId */
    public function __construct(
        public int $accountId,
        public array $assetIdByScriptId,
    ) {}
}
