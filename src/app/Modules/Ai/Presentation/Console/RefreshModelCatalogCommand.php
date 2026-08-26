<?php

declare(strict_types=1);

namespace App\Modules\Ai\Presentation\Console;

use App\Modules\Ai\Application\Services\ModelCatalogRefresher;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Illuminate\Console\Command;

class RefreshModelCatalogCommand extends Command
{
    protected $signature = 'ai:refresh-models {--account= : only this account, instead of every account with a key}';

    protected $description = 'Ask each connected LLM provider which models the account can call, and cache the answer';

    public function handle(ModelCatalogRefresher $refresher, IntegrationService $integrations): int
    {
        $accounts = $this->option('account') !== null
            ? collect([(int) $this->option('account')])
            : $integrations->accountIdsConnectedTo(array_map(
                static fn (LlmProvider $provider): IntegrationProvider => IntegrationProvider::from($provider->value),
                LlmProvider::cases(),
            ));

        if ($accounts->isEmpty()) {
            $this->warn('No account has an LLM credential connected.');

            return self::SUCCESS;
        }

        foreach ($accounts as $accountId) {
            foreach ($refresher->refresh($accountId) as $provider => $count) {
                $this->line("account {$accountId} · {$provider}: {$count} models");
            }
        }

        return self::SUCCESS;
    }
}
