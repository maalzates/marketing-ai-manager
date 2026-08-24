<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Contracts\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Contracts\DriveClientFactoryInterface;
use App\Modules\Assets\Domain\Exceptions\AssetWithoutDriveFileException;
use App\Modules\Assets\Domain\Exceptions\InvalidMediaTokenException;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The seam between "Drive stores, the DB governs" and Instagram's pull-based publishing API,
 * which downloads a public URL and refuses a binary. A minted token is a capability: it names
 * one asset, opens a stream straight from Drive, and dies once the fetch window it opens on
 * first use closes. The file is never written to disk; only the token's nonce is kept, in the
 * cache, and the token itself is never stored.
 */
readonly class AssetStreamService
{
    public const string ROUTE_NAME = 'assets.media';

    private const string CACHE_PREFIX = 'assets:media-token:';

    private const int TTL_HOURS = 24;

    private const int FETCH_WINDOW_MINUTES = 10;

    private const string SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        private AssetRepositoryInterface $repository,
        private DriveClientFactoryInterface $clients,
        private Cache $cache,
        private Encrypter $encrypter,
        private UrlGenerator $url,
    ) {}

    /**
     * The URL lives 24 hours unfetched — the lifetime of an Instagram media container — but only
     * ten minutes past its first fetch. Meta's fetcher issues several requests per container
     * (a HEAD, then ranged GETs on video), so a token good for exactly one request would break
     * publishing; a window that starts on the first byte still shuts a leaked URL long before
     * the container it was minted for expires.
     */
    public function signedUrlFor(Asset $asset): string
    {
        return $this->url->route(self::ROUTE_NAME, ['token' => $this->mint($asset)]);
    }

    public function resolve(string $token): Asset
    {
        $claims = $this->verifiedClaims($token) ?? throw InvalidMediaTokenException::rejected();

        return $this->repository->findById((int) $claims['asset'], (int) $claims['account'])
            ?? throw InvalidMediaTokenException::rejected();
    }

    public function pipe(Asset $asset, mixed $sink): void
    {
        $this->clients->forAccount($asset->account_id)->download(
            $asset->drive_file_id ?? throw AssetWithoutDriveFileException::withId($asset->id),
            $sink,
        );
    }

    private function mint(Asset $asset): string
    {
        $expiresAt = CarbonImmutable::now()->addHours(self::TTL_HOURS);
        $nonce = bin2hex(random_bytes(8));

        $this->cache->put(self::CACHE_PREFIX.$nonce, false, $expiresAt);

        return $this->sign(self::encode((string) json_encode([
            'asset' => $asset->id,
            'account' => $asset->account_id,
            'expires' => $expiresAt->getTimestamp(),
            'nonce' => $nonce,
        ])));
    }

    private function sign(string $payload): string
    {
        return $payload.'.'.self::encode(hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $this->encrypter->getKey(), true));
    }

    /**
     * @return array{asset: int, account: int, nonce: string}|null
     */
    private function verifiedClaims(string $token): ?array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! hash_equals(self::encode(hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $this->encrypter->getKey(), true)), $signature)) {
            return null;
        }

        return $this->withinFetchWindow(self::unexpired((array) json_decode(self::decode($payload), true)));
    }

    /**
     * The cached nonce carries whether the window is already open: absent means never minted,
     * spent or timed out; `false` means this is the first fetch and starts the clock.
     */
    private function withinFetchWindow(?array $claims): ?array
    {
        if ($claims === null) {
            return null;
        }

        $key = self::CACHE_PREFIX.($claims['nonce'] ?? '');
        $opened = $this->cache->get($key);

        if ($opened === false) {
            $this->cache->put($key, true, CarbonImmutable::now()->addMinutes(self::FETCH_WINDOW_MINUTES));
        }

        return $opened === null ? null : $claims;
    }

    private static function unexpired(array $claims): ?array
    {
        return isset($claims['asset'], $claims['account'], $claims['expires'], $claims['nonce'])
            && $claims['expires'] > CarbonImmutable::now()->getTimestamp()
                ? $claims
                : null;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
