<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Core\Domain\Support\SecretMasker;
use App\Modules\Integrations\Application\DTO\ConnectApiKeyDTO;
use App\Modules\Integrations\Domain\Contracts\IntegrationRepositoryInterface;
use App\Modules\Integrations\Domain\Enums\IntegrationKind;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Domain\Enums\VerificationFailure;
use App\Modules\Integrations\Domain\Exceptions\IntegrationNotConnectedException;
use App\Modules\Integrations\Domain\Exceptions\IntegrationVerificationFailedException;
use App\Modules\Integrations\Domain\Exceptions\UnsupportedIntegrationProviderException;
use App\Modules\Integrations\Domain\Support\VerificationOutcome;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use Illuminate\Support\Collection;

readonly class IntegrationService
{
    public function __construct(
        private IntegrationRepositoryInterface $repository,
        private IntegrationVerifier $verifier,
        private SecretMasker $masker,
    ) {}

    /**
     * Every provider is listed, connected or not, so the settings screen renders the same
     * rows for a brand new account as for a fully configured one.
     *
     * @return Collection<int, Integration>
     */
    public function list(int $accountId): Collection
    {
        $stored = $this->repository->allForAccount($accountId)
            ->keyBy(fn (Integration $integration): string => $integration->provider->value);

        return collect(IntegrationProvider::cases())->map(
            fn (IntegrationProvider $provider): Integration => $stored->get($provider->value)
                ?? self::unconnected($accountId, $provider),
        );
    }

    /**
     * Which providers this account can actually call right now. Callers outside the module
     * ask this instead of reading a status off an Integration, which is not theirs to touch.
     *
     * @return Collection<int, IntegrationProvider>
     */
    public function connectedProviders(int $accountId): Collection
    {
        return $this->repository->allForAccount($accountId)
            ->filter(static fn (Integration $integration): bool => $integration->status === IntegrationStatus::CONNECTED)
            ->map(static fn (Integration $integration): IntegrationProvider => $integration->provider)
            ->values();
    }

    /**
     * Which accounts a scheduled job has to visit. Asked here rather than by scanning every
     * account, so an account that never connected a provider costs nothing.
     *
     * @param  list<IntegrationProvider>  $providers
     * @return Collection<int, int>
     */
    public function accountIdsConnectedTo(array $providers): Collection
    {
        return $this->repository->accountIdsConnectedTo($providers);
    }

    public function connectApiKey(ConnectApiKeyDTO $dto): Integration
    {
        if ($dto->provider->kind() !== IntegrationKind::API_KEY) {
            throw UnsupportedIntegrationProviderException::forApiKey($dto->provider);
        }

        $integration = $this->repository->storeApiKey($dto);
        $outcome = $this->verifier->verify($integration);

        if (! $outcome->valid) {
            $this->recordFailure($integration, $outcome);

            throw IntegrationVerificationFailedException::forProvider(
                $dto->provider,
                $outcome->failure,
                $this->masker->mask($outcome->diagnosis),
            );
        }

        return $this->markHealthy($integration, $outcome->externalAccountId);
    }

    public function disconnect(int $accountId, IntegrationProvider $provider): void
    {
        $this->repository->delete($this->stored($accountId, $provider));
    }

    public function verify(int $accountId, IntegrationProvider $provider): Integration
    {
        $integration = $this->stored($accountId, $provider);
        $outcome = $this->verifier->verify($integration);

        return $outcome->valid
            ? $this->markHealthy($integration, $outcome->externalAccountId)
            : $this->recordFailure($integration, $outcome);
    }

    public function markHealthy(Integration $integration, ?string $externalAccountId = null): Integration
    {
        return $this->repository->markHealthy($integration, $externalAccountId);
    }

    public function markFailure(Integration $integration, IntegrationStatus $status, array $diagnosis): Integration
    {
        return $this->repository->markFailure($integration, $status, $this->masker->mask($diagnosis));
    }

    public function stored(int $accountId, IntegrationProvider $provider): Integration
    {
        return $this->repository->findByProvider($accountId, $provider)
            ?? throw IntegrationNotConnectedException::forProvider($provider, $accountId);
    }

    private function recordFailure(Integration $integration, VerificationOutcome $outcome): Integration
    {
        return $this->markFailure(
            $integration,
            self::failureStatus($integration, $outcome),
            ['failure' => $outcome->failure->value, ...$outcome->diagnosis],
        );
    }

    /**
     * A provider that did not answer says nothing about the credential, so the row keeps
     * the status it had; only the check timestamp and the failure counter move. A grant
     * the provider itself rejected is spent, not misconfigured.
     */
    private static function failureStatus(Integration $integration, VerificationOutcome $outcome): IntegrationStatus
    {
        return match (true) {
            $outcome->failure === VerificationFailure::PROVIDER_UNAVAILABLE => $integration->status,
            $outcome->failure === VerificationFailure::CREDENTIAL_REJECTED
                && $integration->kind === IntegrationKind::OAUTH => IntegrationStatus::EXPIRED,
            default => IntegrationStatus::ERROR,
        };
    }

    private static function unconnected(int $accountId, IntegrationProvider $provider): Integration
    {
        return new Integration([
            'account_id' => $accountId,
            'provider' => $provider,
            'kind' => $provider->kind(),
            'status' => IntegrationStatus::DISCONNECTED,
            'failure_count' => 0,
        ]);
    }
}
