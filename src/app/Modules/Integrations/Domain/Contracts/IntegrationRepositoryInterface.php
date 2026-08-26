<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Contracts;

use App\Modules\Integrations\Application\DTO\ConnectApiKeyDTO;
use App\Modules\Integrations\Application\DTO\StoreOAuthCredentialsDTO;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface IntegrationRepositoryInterface
{
    /** @return Collection<int, Integration> */
    public function allForAccount(int $accountId): Collection;

    public function findByProvider(int $accountId, IntegrationProvider $provider): ?Integration;

    public function storeApiKey(ConnectApiKeyDTO $dto): Integration;

    public function storeOAuthCredentials(StoreOAuthCredentialsDTO $dto): Integration;

    public function replaceAccessToken(Integration $integration, string $accessToken, ?CarbonInterface $expiresAt): Integration;

    public function markHealthy(Integration $integration, ?string $externalAccountId): Integration;

    public function markFailure(Integration $integration, IntegrationStatus $status, array $diagnosis): Integration;

    public function delete(Integration $integration): bool;

    /** @return Collection<int, Integration> */
    public function oauthExpiringFor(int $accountId, CarbonInterface $threshold): Collection;

    /** @return Collection<int, int> */
    public function accountIdsWithOAuthIntegrations(): Collection;

    /**
     * @param  list<IntegrationProvider>  $providers
     * @return Collection<int, int>
     */
    public function accountIdsConnectedTo(array $providers): Collection;
}
