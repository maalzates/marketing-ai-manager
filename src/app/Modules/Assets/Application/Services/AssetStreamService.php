<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Contracts\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Contracts\DriveClientFactoryInterface;
use App\Modules\Assets\Domain\Exceptions\AssetWithoutDriveFileException;
use App\Modules\Assets\Domain\Exceptions\InvalidMediaTokenException;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * The seam between "Drive stores, the DB governs" and Instagram's pull-based publishing API,
 * which downloads a public URL and refuses a binary. A minted token is a capability: it names
 * one asset, expires with the media container that consumes it, and opens a stream straight
 * from Drive. Nothing is ever written to disk and no token is stored.
 */
readonly class AssetStreamService
{
    public const string ROUTE_NAME = 'assets.media';

    private const int TTL_HOURS = 24;

    private const string SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(
        private AssetRepositoryInterface $repository,
        private DriveClientFactoryInterface $clients,
        private Encrypter $encrypter,
        private UrlGenerator $url,
    ) {}

    /**
     * 24 hours matches the lifetime of an Instagram media container, and the nonce makes every
     * mint a distinct token, so one container's URL never serves another's.
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
        return $this->sign(self::encode(json_encode([
            'asset' => $asset->id,
            'account' => $asset->account_id,
            'expires' => CarbonImmutable::now()->addHours(self::TTL_HOURS)->getTimestamp(),
            'nonce' => bin2hex(random_bytes(8)),
        ])));
    }

    private function sign(string $payload): string
    {
        return $payload.'.'.self::encode(hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $this->encrypter->getKey(), true));
    }

    /**
     * @return array{asset: int, account: int}|null
     */
    private function verifiedClaims(string $token): ?array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');

        if (! hash_equals(self::encode(hash_hmac(self::SIGNATURE_ALGORITHM, $payload, $this->encrypter->getKey(), true)), $signature)) {
            return null;
        }

        return self::unexpired((array) json_decode(self::decode($payload), true));
    }

    private static function unexpired(array $claims): ?array
    {
        return isset($claims['asset'], $claims['account'], $claims['expires'])
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
