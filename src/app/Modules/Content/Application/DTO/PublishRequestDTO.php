<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\DTO;

use App\Modules\Content\Domain\Enums\ContentFormat;

/**
 * What a channel needs to publish one piece. `mediaUrls` are already-signed, publicly
 * reachable URLs: every channel this project supports pulls the bytes itself instead of
 * accepting an upload.
 */
readonly class PublishRequestDTO
{
    /** @param  list<string>  $mediaUrls */
    public function __construct(
        public int $accountId,
        public int $scheduleId,
        public ContentFormat $format,
        public array $mediaUrls,
        public string $caption,
        public ?string $coverUrl = null,
    ) {}
}
