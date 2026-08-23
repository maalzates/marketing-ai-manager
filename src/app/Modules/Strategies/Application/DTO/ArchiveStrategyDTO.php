<?php

declare(strict_types=1);

namespace App\Modules\Strategies\Application\DTO;

use App\Modules\Audit\Domain\Enums\ActionOrigin;

/**
 * Archiving is the one transition that ends a strategy's life, so the door has to say who
 * asked for it and from where — the action log entry is part of the operation.
 */
readonly class ArchiveStrategyDTO
{
    public function __construct(
        public int $accountId,
        public int $strategyId,
        public ?int $userId,
        public ActionOrigin $origin,
    ) {}
}
