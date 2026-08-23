<?php

declare(strict_types=1);

namespace App\Modules\Competitors\Domain\Contracts;

use Illuminate\Support\Collection;

/**
 * The half of the novelty filter that lives outside this module: a mined idea must not
 * repeat a hypothesis the account already tested and refuted. Bound to a null source
 * until the Experiments module exposes its verdict history as a Service.
 */
interface RefutedHypothesisSourceInterface
{
    /**
     * @return Collection<int, string>
     */
    public function refutedHypotheses(int $accountId): Collection;
}
