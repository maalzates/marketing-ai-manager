<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Infrastructure\Repositories;

use App\Modules\Integrations\Application\DTO\ConnectApiKeyDTO;
use App\Modules\Integrations\Application\DTO\StoreOAuthCredentialsDTO;
use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationKind;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Domain\Exceptions\IntegrationPersistenceFailedException;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

readonly class IntegrationRepository implements IntegrationRepositoryInterface
{
    public function __construct(private Integration $model) {}

    public function allForAccount(int $accountId): Collection
    {
        return $this->model->newQuery()->where('account_id', $accountId)->get();
    }

    public function findByProvider(int $accountId, IntegrationProvider $provider): ?Integration
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('provider', $provider->value)
            ->first();
    }

    public function storeApiKey(ConnectApiKeyDTO $dto): Integration
    {
        try {
            return $this->upsert($dto->accountId, $dto->provider, [
                'kind' => IntegrationKind::API_KEY,
                'credentials' => ['api_key' => $dto->apiKey],
                'status' => IntegrationStatus::DISCONNECTED,
                'external_account_id' => null,
                'scopes' => null,
                'expires_at' => null,
                'last_error' => null,
                'failure_count' => 0,
            ]);
        } catch (Throwable $exception) {
            throw IntegrationPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'provider' => $dto->provider->value,
            ]);
        }
    }

    public function storeOAuthCredentials(StoreOAuthCredentialsDTO $dto): Integration
    {
        try {
            return $this->upsert($dto->accountId, $dto->provider, [
                'kind' => IntegrationKind::OAUTH,
                'credentials' => $dto->credentials,
                'status' => IntegrationStatus::CONNECTED,
                'external_account_id' => $dto->externalAccountId,
                'scopes' => $dto->scopes,
                'expires_at' => $dto->expiresAt,
                'last_checked_at' => now(),
                'last_error' => null,
                'failure_count' => 0,
            ]);
        } catch (Throwable $exception) {
            throw IntegrationPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'provider' => $dto->provider->value,
            ]);
        }
    }

    public function replaceAccessToken(Integration $integration, string $accessToken, ?CarbonInterface $expiresAt): Integration
    {
        try {
            $integration->update([
                // Google omits the refresh token on a refresh response; the stored one stays.
                'credentials' => [...$integration->credentials, 'access_token' => $accessToken],
                'status' => IntegrationStatus::CONNECTED,
                'expires_at' => $expiresAt,
                'last_error' => null,
                'failure_count' => 0,
            ]);

            return $integration->refresh();
        } catch (Throwable $exception) {
            throw IntegrationPersistenceFailedException::wrap($exception, context: [
                'integration_id' => $integration->id,
            ]);
        }
    }

    public function markHealthy(Integration $integration, ?string $externalAccountId): Integration
    {
        try {
            $integration->update([
                'status' => IntegrationStatus::CONNECTED,
                'external_account_id' => $externalAccountId ?? $integration->external_account_id,
                'last_checked_at' => now(),
                'last_error' => null,
                'failure_count' => 0,
            ]);

            return $integration->refresh();
        } catch (Throwable $exception) {
            throw IntegrationPersistenceFailedException::wrap($exception, context: [
                'integration_id' => $integration->id,
            ]);
        }
    }

    public function markFailure(Integration $integration, IntegrationStatus $status, array $diagnosis): Integration
    {
        try {
            $integration->update([
                'status' => $status,
                'last_checked_at' => now(),
                'last_error' => json_encode($diagnosis, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'failure_count' => $integration->failure_count + 1,
            ]);

            return $integration->refresh();
        } catch (Throwable $exception) {
            throw IntegrationPersistenceFailedException::wrap($exception, context: [
                'integration_id' => $integration->id,
            ]);
        }
    }

    public function delete(Integration $integration): bool
    {
        return (bool) $integration->delete();
    }

    public function oauthExpiringFor(int $accountId, CarbonInterface $threshold): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->where('kind', IntegrationKind::OAUTH->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $threshold)
            ->get();
    }

    public function accountIdsWithOAuthIntegrations(): Collection
    {
        return $this->model->newQuery()
            ->where('kind', IntegrationKind::OAUTH->value)
            ->distinct()
            ->pluck('account_id');
    }

    public function accountIdsConnectedTo(array $providers): Collection
    {
        return $this->model->newQuery()
            ->whereIn('provider', array_map(static fn (IntegrationProvider $provider): string => $provider->value, $providers))
            ->where('status', IntegrationStatus::CONNECTED->value)
            ->distinct()
            ->pluck('account_id');
    }

    private function upsert(int $accountId, IntegrationProvider $provider, array $attributes): Integration
    {
        return $this->model->newQuery()->updateOrCreate(
            ['account_id' => $accountId, 'provider' => $provider],
            $attributes,
        );
    }
}
