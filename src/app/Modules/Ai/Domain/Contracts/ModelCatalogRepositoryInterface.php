<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Contracts;

use App\Modules\Ai\Domain\Enums\LlmProvider;
use Illuminate\Support\Collection;

interface ModelCatalogRepositoryInterface
{
    /** @return Collection<int, string> empty when this account never refreshed that provider */
    public function idsFor(int $accountId, LlmProvider $provider): Collection;

    /** @param  Collection<int, string>  $ids */
    public function store(int $accountId, LlmProvider $provider, Collection $ids): void;
}
