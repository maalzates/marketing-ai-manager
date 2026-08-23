<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Contracts;

use App\Modules\Integrations\Infrastructure\Clients\GoogleOAuthClient;

interface GoogleOAuthClientFactoryInterface
{
    /** Token, refresh and revoke calls authenticate with the platform's OAuth client. */
    public function create(): GoogleOAuthClient;

    /** Userinfo authenticates with the account's own bearer token, never a shared one. */
    public function forAccessToken(string $accessToken): GoogleOAuthClient;
}
