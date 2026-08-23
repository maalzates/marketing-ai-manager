<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Application\DTO;

use Illuminate\Support\Collection;

/**
 * One provider call: what it produced and what it cost. The cost travels with the items
 * because the consumption ledger has to be written by whoever persists the result, and
 * only the provider knows the run it came from.
 *
 * @template TItem of CompetitorPostDTO|CompetitorCommentDTO
 */
readonly class ProviderRunResultDTO
{
    /** @param Collection<int, TItem> $items */
    public function __construct(
        public string $actorId,
        public ?string $runId,
        public Collection $items,
        public float $costUsd,
    ) {}
}
