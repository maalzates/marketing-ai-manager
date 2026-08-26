<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Presentation\Http\Controllers\Api;

use App\Modules\Core\Application\Context\AccountContext;
use App\Modules\Core\Presentation\Http\Controllers\Api\ApiController;
use App\Modules\Core\Presentation\Http\Responses\OAuthCallbackRedirect;
use App\Modules\Integrations\Application\Services\IntegrationOAuthService;
use App\Modules\Integrations\Presentation\Http\Requests\IntegrationProviderRequest;
use App\Modules\Integrations\Presentation\Http\Requests\OAuthCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class IntegrationOAuthController extends ApiController
{
    public function __construct(private readonly IntegrationOAuthService $service)
    {
        parent::__construct();
    }

    /**
     * Returns the URL instead of a 302: the SPA — and a future mobile client — need to open
     * the consent screen themselves, not follow a redirect issued to an XHR.
     */
    public function redirect(IntegrationProviderRequest $request, AccountContext $account): JsonResponse
    {
        return $this->response->success([
            'url' => $this->service->authorisationUrl($account->accountId, $request->provider()),
        ]);
    }

    /**
     * Public: the provider calls it. The signed, single-use `state` carries the account.
     *
     * Answers with a redirect rather than the JSON envelope because the caller is a browser
     * mid-navigation, not an XHR. The stored grant is not echoed back — the SPA reads it from
     * `GET /integrations` like any other page load.
     */
    public function callback(OAuthCallbackRequest $request): RedirectResponse
    {
        $dto = $request->toDTO();

        $this->service->completeCallback($dto);

        return OAuthCallbackRedirect::connected($dto->provider->value);
    }
}
