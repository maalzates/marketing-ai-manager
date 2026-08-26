<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Jobs;

use App\Modules\Ai\Application\Services\ModelCatalogRefresher;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One account, or every account with an LLM credential. Accounts without one are never
 * visited: there is nobody to ask, and asking would need a key that does not exist.
 */
class RefreshModelCatalogJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?int $accountId = null) {}

    public function handle(ModelCatalogRefresher $refresher, IntegrationService $integrations): void
    {
        $accounts = $this->accountId === null
            ? $integrations->accountIdsConnectedTo(self::llmProviders())
            : collect([$this->accountId]);

        $accounts->each(fn (int $accountId) => $refresher->refresh($accountId));
    }

    /** @return list<IntegrationProvider> */
    private static function llmProviders(): array
    {
        return array_map(
            static fn (LlmProvider $provider): IntegrationProvider => IntegrationProvider::from($provider->value),
            LlmProvider::cases(),
        );
    }
}
