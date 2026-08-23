<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Services;

use App\Modules\Assets\Application\Services\AssetService;
use App\Modules\Assets\Application\Services\AssetStreamService;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use App\Modules\Content\Application\DTO\ChannelMediaSpecDTO;
use App\Modules\Content\Domain\Exceptions\MediaSpecRejectedException;

/**
 * Turns a linked asset into the public URLs a channel will pull from. Publishing is pull-based
 * everywhere this project posts: the bytes never leave Drive through us, only a signed URL that
 * streams them for as long as a container can live.
 */
readonly class PublishableMediaResolver
{
    public function __construct(
        private AssetService $assets,
        private AssetStreamService $stream,
    ) {}

    /**
     * @return list<string>
     */
    public function urlsFor(int $assetId, int $accountId, ChannelMediaSpecDTO $spec): array
    {
        $asset = $this->assets->find($assetId, $accountId);

        return $asset->type === AssetType::Carousel
            ? $asset->slides->map(fn (Asset $slide): string => $this->signed($slide, $spec))->values()->all()
            : [$this->signed($asset, $spec)];
    }

    private function signed(Asset $asset, ChannelMediaSpecDTO $spec): string
    {
        $violations = self::violations($asset, $spec);

        return $violations === []
            ? $this->stream->signedUrlFor($asset)
            : throw MediaSpecRejectedException::withViolations((int) $asset->id, $violations);
    }

    /**
     * Only what Drive's own metadata can prove. The `moov` atom has to sit at the front of the
     * file and nothing outside the bytes says whether it does — that one surfaces later, as a
     * container in `status_code=ERROR`.
     *
     * @return list<string>
     */
    private static function violations(Asset $asset, ChannelMediaSpecDTO $spec): array
    {
        return $asset->type->isVideo()
            ? array_values(array_filter([
                self::mimeViolation($asset, $spec->videoMimeTypes),
                self::sizeViolation($asset, $spec->maxVideoBytes),
                self::durationViolation($asset, $spec),
            ]))
            : array_values(array_filter([
                self::mimeViolation($asset, $spec->imageMimeTypes),
                self::sizeViolation($asset, $spec->maxImageBytes),
            ]));
    }

    /** @param  list<string>  $allowed */
    private static function mimeViolation(Asset $asset, array $allowed): ?string
    {
        return $asset->mime_type === null || in_array($asset->mime_type, $allowed, true)
            ? null
            : sprintf('%s is not accepted (allowed: %s).', $asset->mime_type, implode(', ', $allowed));
    }

    private static function sizeViolation(Asset $asset, int $maxBytes): ?string
    {
        return ($asset->size_bytes ?? 0) <= $maxBytes
            ? null
            : sprintf('The file weighs %d bytes, over the %d byte limit.', $asset->size_bytes, $maxBytes);
    }

    private static function durationViolation(Asset $asset, ChannelMediaSpecDTO $spec): ?string
    {
        return $asset->duration_seconds === null
            || ($asset->duration_seconds >= $spec->minVideoSeconds && $asset->duration_seconds <= $spec->maxVideoSeconds)
            ? null
            : sprintf(
                'The video lasts %ds, outside the accepted %d–%ds.',
                $asset->duration_seconds,
                $spec->minVideoSeconds,
                $spec->maxVideoSeconds,
            );
    }
}
