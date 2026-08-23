<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Exceptions\InvalidOAuthStateException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * The sign-in callback is public and, unlike the Integrations flow, carries no account to
 * bind to — the state exists purely to prove this redirect answers a request we issued.
 * So it is an opaque nonce held server-side and pulled on use: unguessable, single-use and
 * expiring on its own. Nothing the client sends back is trusted beyond matching it.
 */
readonly class LoginStateManager
{
    private const string CACHE_PREFIX = 'auth:login-state:';

    private const int LIFETIME_MINUTES = 10;

    private const int NONCE_LENGTH = 40;

    public function __construct(private Cache $cache) {}

    public function issue(): string
    {
        $nonce = Str::random(self::NONCE_LENGTH);

        $this->cache->put(self::CACHE_PREFIX.$nonce, true, now()->addMinutes(self::LIFETIME_MINUTES));

        return $nonce;
    }

    public function consume(string $state): void
    {
        if ($this->cache->pull(self::CACHE_PREFIX.$state) === null) {
            throw InvalidOAuthStateException::rejected('state_already_used_or_expired');
        }
    }
}
