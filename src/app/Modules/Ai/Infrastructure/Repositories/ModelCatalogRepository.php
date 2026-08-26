<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure\Repositories;

use App\Modules\Ai\Domain\Contracts\ModelCatalogRepositoryInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

/**
 * The catalogue is cached per account, not globally: `GET /models` answers what that
 * organisation is entitled to call, so one account's list is not another's.
 *
 * There is no TTL. A stale list is still the truth about what was callable yesterday, while an
 * expired one would be indistinguishable from "never refreshed" — and that case falls back to
 * the priced catalogue in config, quietly widening what the UI offers.
 */
readonly class ModelCatalogRepository implements ModelCatalogRepositoryInterface
{
    private const string KEY_PREFIX = 'ai:model-catalog';

    public function __construct(private Cache $cache) {}

    public function idsFor(int $accountId, LlmProvider $provider): Collection
    {
        return collect($this->cache->get(self::key($accountId, $provider), []));
    }

    public function store(int $accountId, LlmProvider $provider, Collection $ids): void
    {
        $this->cache->forever(self::key($accountId, $provider), $ids->values()->all());
    }

    private static function key(int $accountId, LlmProvider $provider): string
    {
        return self::KEY_PREFIX.":{$accountId}:{$provider->value}";
    }
}
