<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Exceptions\InvalidOAuthStateException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * The callback is a public route, so the `state` is the only thing tying the redirect back
 * to the account that started it. It is HMAC-signed so a forged one is rejected without a
 * lookup, and its nonce is pulled from the cache on use so a replayed one is rejected too.
 */
readonly class OAuthStateManager
{
    private const string CACHE_PREFIX = 'integrations:oauth-state:';

    private const int LIFETIME_MINUTES = 10;

    private const int NONCE_LENGTH = 40;

    public function __construct(private Cache $cache) {}

    public function issue(int $accountId, IntegrationProvider $provider): string
    {
        $payload = self::encode([
            'account_id' => $accountId,
            'provider' => $provider->value,
            'nonce' => $nonce = Str::random(self::NONCE_LENGTH),
        ]);

        $this->cache->put(self::CACHE_PREFIX.$nonce, true, now()->addMinutes(self::LIFETIME_MINUTES));

        return $payload.'.'.self::sign($payload);
    }

    public function consume(?string $state, IntegrationProvider $provider): int
    {
        [$payload, $signature] = array_pad(explode('.', (string) $state, 2), 2, '');

        if (! hash_equals(self::sign($payload), $signature)) {
            throw InvalidOAuthStateException::rejected('signature_mismatch');
        }

        return self::accountIdFrom($this->claim(self::decode($payload)), $provider);
    }

    private function claim(array $claims): array
    {
        return $this->cache->pull(self::CACHE_PREFIX.($claims['nonce'] ?? '')) === null
            ? throw InvalidOAuthStateException::rejected('state_already_used_or_expired')
            : $claims;
    }

    private static function accountIdFrom(array $claims, IntegrationProvider $provider): int
    {
        return ($claims['provider'] ?? null) === $provider->value
            ? (int) $claims['account_id']
            : throw InvalidOAuthStateException::rejected('provider_mismatch');
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private static function encode(array $claims): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');
    }

    private static function decode(string $payload): array
    {
        return (array) json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
    }
}
