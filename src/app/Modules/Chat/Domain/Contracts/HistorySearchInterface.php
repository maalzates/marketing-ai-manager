<?php

declare(strict_types=1);

namespace App\Modules\Chat\Domain\Contracts;

use App\Modules\Chat\Application\DTO\HistorySearchDTO;
use App\Modules\Chat\Application\Services\KeywordHistorySearch;
use Illuminate\Support\Collection;

/**
 * The seam `core.md` §4 layer 3 upgrades: today the binding is the lexical
 * KeywordHistorySearch; swapping it for an embedding-backed implementation in
 * ChatServiceProvider changes how search_history retrieves and nothing else.
 *
 * @see KeywordHistorySearch
 */
interface HistorySearchInterface
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(HistorySearchDTO $dto): Collection;
}
