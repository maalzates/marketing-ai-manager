<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Contracts\ModelCatalogRepositoryInterface;
use App\Modules\Ai\Domain\Contracts\ModelListClientFactoryInterface;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Domain\Exceptions\ModelListUnavailableException;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Support\Collection;

/**
 * Asks each connected provider which models this account can call, and stores the answer.
 *
 * A provider that fails is skipped rather than fatal: the other two still refresh, and the one
 * that failed keeps serving its last known list. Losing the whole catalogue because one
 * provider had a bad minute would take the model selector away from the user.
 */
readonly class ModelCatalogRefresher
{
    public function __construct(
        private ModelListClientFactoryInterface $clients,
        private ModelCatalogRepositoryInterface $repository,
        private IntegrationService $integrations,
    ) {}

    /** @return Collection<string, int> provider => how many models it listed */
    public function refresh(int $accountId): Collection
    {
        return $this->connectedProviders($accountId)
            ->mapWithKeys(fn (LlmProvider $provider): array => [
                $provider->value => $this->refreshOne($accountId, $provider),
            ]);
    }

    private function refreshOne(int $accountId, LlmProvider $provider): int
    {
        try {
            $ids = $this->clients->forAccount($accountId, $provider)->list();
        } catch (ModelListUnavailableException) {
            return 0;
        }

        $this->repository->store($accountId, $provider, $ids);

        return $ids->count();
    }

    /** @return Collection<int, LlmProvider> */
    private function connectedProviders(int $accountId): Collection
    {
        return $this->integrations->connectedProviders($accountId)
            ->map(static fn (IntegrationProvider $provider): ?LlmProvider => LlmProvider::tryFrom($provider->value))
            ->filter()
            ->values();
    }
}
