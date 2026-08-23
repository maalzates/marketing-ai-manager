<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Infrastructure\Adapters;

use App\Modules\Competitors\Domain\Contracts\RefutedHypothesisSourceInterface;
use Illuminate\Support\Collection;

/**
 * Placeholder binding until the Experiments module exposes verdict history as a Service.
 * Reading the `experiments` table from here would couple this module to another module's
 * persistence, which is the one dependency the architecture forbids.
 */
readonly class NoRefutedHypothesisSource implements RefutedHypothesisSourceInterface
{
    public function refutedHypotheses(int $accountId): Collection
    {
        return collect();
    }
}
