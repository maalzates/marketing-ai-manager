<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\DTO;

/**
 * A null accountId writes the global default; a present one writes that account's
 * override, which is how a per-user rate limit is set without moving the default.
 */
readonly class WriteGlobalSettingsDTO
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public ?int $accountId,
        public array $values,
        public int $actorUserId,
    ) {}
}
