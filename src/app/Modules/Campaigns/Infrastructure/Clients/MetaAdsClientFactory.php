<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Infrastructure\Clients;

use App\Modules\Accounts\Application\DTO\AccountFilterDTO;
use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Campaigns\Domain\Exceptions\AdAccountNotConfiguredException;
use App\Modules\Campaigns\Domain\ValueObjects\AdsAccountTarget;
use App\Modules\Core\Infrastructure\Clients\GuzzleClientFactory;
use App\Modules\Integrations\Application\Services\CredentialResolver;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Settings\Application\Services\SettingsService;

/**
 * The factory is shared; the clients it builds are not. A client bound as a singleton
 * would carry the first account's token — and, worse, the first account's ad account —
 * into every later request.
 */
readonly class MetaAdsClientFactory
{
    private const string AD_ACCOUNT_SETTING = 'campaigns.meta_ad_account_id';

    private const string SANDBOX_AD_ACCOUNT_SETTING = 'campaigns.meta_sandbox_ad_account_id';

    public function __construct(
        private GuzzleClientFactory $guzzle,
        private CredentialResolver $credentials,
        private SettingsService $settings,
        private AccountService $accounts,
    ) {}

    public function forAccount(AdsAccountTarget $target): MetaAdsClient
    {
        return new MetaAdsClient(
            $this->guzzle->create([
                'base_uri' => config('services.meta.graph_base_url'),
                'headers' => [
                    'Authorization' => 'Bearer '.$this->credentials->accessToken(
                        $target->accountId,
                        IntegrationProvider::META,
                    ),
                ],
            ]),
            (string) config('services.meta.graph_version'),
            $this->adAccountId($target),
            (string) $this->accounts->findActiveById(new AccountFilterDTO($target->accountId))->currency,
        );
    }

    /**
     * Sandbox is not a flag on the call: it is a different ad account, so switching modes
     * switches which account every campaign, budget and insight in the module refers to.
     */
    private function adAccountId(AdsAccountTarget $target): string
    {
        $key = $target->sandbox ? self::SANDBOX_AD_ACCOUNT_SETTING : self::AD_ACCOUNT_SETTING;
        $adAccountId = (string) $this->settings->get($key, $target->accountId);

        return $adAccountId === ''
            ? throw AdAccountNotConfiguredException::forAccount($target->accountId, $target->sandbox, $key)
            : $adAccountId;
    }
}
