<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Contracts\ModelCatalogRepositoryInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Integrations\Domain\Contracts\LlmModelCatalogInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Support\Collection;

/**
 * The union of two sources that neither of them can replace.
 *
 * The provider says which models exist for this credential; **no provider serves prices** —
 * there is no pricing endpoint on any of the three, and the rates live on a web page that
 * moves. So `config/services.php` stays the tariff, and the ledger bills from it.
 *
 * A model is only selectable when both agree. One that is listed but not priced cannot be
 * billed, and one that is priced but no longer listed cannot be called — both are shown,
 * disabled, because hiding them is how an operator ends up wondering where a model went.
 */
readonly class LlmModelCatalog implements LlmModelCatalogInterface
{
    public const string AVAILABLE = 'available';

    public const string UNPRICED = 'unpriced';

    public const string RETIRED = 'retired';

    public function __construct(private ModelCatalogRepositoryInterface $repository) {}

    public function pricingUrlFor(IntegrationProvider $provider): ?string
    {
        return LlmProvider::tryFrom($provider->value)?->pricingUrl();
    }

    public function forProvider(IntegrationProvider $provider, int $accountId): array
    {
        $llm = LlmProvider::tryFrom($provider->value);

        if ($llm === null) {
            return [];
        }

        $priced = collect($llm->models());
        $live = $this->repository->idsFor($accountId, $llm);

        // Never refreshed: the priced catalogue is all there is, and everything in it is
        // offered. An empty live list must not read as "the provider retired everything".
        return $live->isEmpty()
            ? self::pricedOnly($priced)
            : self::merged($priced, $live);
    }

    /** @param  Collection<string, array{input: float, output: float}>  $priced */
    private static function pricedOnly(Collection $priced): array
    {
        return $priced->map(static fn (array $prices, string $id): array => [
            'id' => $id,
            'input' => (float) $prices['input'],
            'output' => (float) $prices['output'],
            'state' => self::AVAILABLE,
        ])->values()->all();
    }

    /**
     * @param  Collection<string, array{input: float, output: float}>  $priced
     * @param  Collection<int, string>  $live
     */
    private static function merged(Collection $priced, Collection $live): array
    {
        return $live->map(static fn (string $id): array => [
            'id' => $id,
            'input' => isset($priced[$id]) ? (float) $priced[$id]['input'] : null,
            'output' => isset($priced[$id]) ? (float) $priced[$id]['output'] : null,
            'state' => isset($priced[$id]) ? self::AVAILABLE : self::UNPRICED,
        ])
            ->merge($priced->reject(static fn (array $prices, string $id): bool => $live->contains($id))
                ->map(static fn (array $prices, string $id): array => [
                    'id' => $id,
                    'input' => (float) $prices['input'],
                    'output' => (float) $prices['output'],
                    'state' => self::RETIRED,
                ])->values())
            ->sortBy([['state', 'asc'], ['output', 'asc']])
            ->values()
            ->all();
    }
}
