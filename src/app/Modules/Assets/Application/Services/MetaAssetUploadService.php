<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Contracts\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Contracts\MetaMediaLibraryInterface;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use App\Modules\Assets\Domain\Exceptions\AssetBrokenException;
use App\Modules\Assets\Domain\Exceptions\AssetNotFoundException;
use App\Modules\Assets\Domain\Exceptions\AssetWithoutDriveFileException;
use App\Modules\Assets\Domain\ValueObjects\MediaFilename;
use App\Modules\Assets\Infrastructure\Persistence\Asset;

/**
 * Campaigns calls this when a piece is used in an ad. The upload happens exactly once per
 * asset; afterwards `meta_asset_id` is the answer and Meta is never asked again.
 */
readonly class MetaAssetUploadService
{
    public function __construct(
        private AssetRepositoryInterface $repository,
        private AssetStreamService $stream,
        private MetaMediaLibraryInterface $library,
    ) {}

    public function ensureUploaded(int $accountId, int $assetId): Asset
    {
        $asset = $this->repository->findById($assetId, $accountId) ?? throw AssetNotFoundException::withId($assetId);

        return $asset->meta_asset_id !== null
            ? $asset
            : $this->repository->cacheMetaAsset($asset, $this->push($accountId, $this->publishable($asset)), self::typeOf($asset));
    }

    /**
     * Meta rejects an image whose filename has no extension, and a linked Drive file keeps the
     * name its owner gave it — so the name declared here is derived, never the stored one.
     */
    private function push(int $accountId, Asset $asset): string
    {
        return $asset->type->isVideo()
            ? $this->library->uploadVideo($accountId, $this->declaredName($asset), $this->stream->signedUrlFor($asset))
            : $this->library->uploadImage($accountId, $this->declaredName($asset), $this->stream->signedUrlFor($asset));
    }

    private function declaredName(Asset $asset): string
    {
        return (new MediaFilename($asset->name, $asset->mime_type))->withExtension();
    }

    private function publishable(Asset $asset): Asset
    {
        return match (true) {
            $asset->status === AssetStatus::Broken => throw AssetBrokenException::withId($asset->id),
            $asset->drive_file_id === null => throw AssetWithoutDriveFileException::withId($asset->id),
            default => $asset,
        };
    }

    private static function typeOf(Asset $asset): MetaAssetType
    {
        return $asset->type->isVideo() ? MetaAssetType::VideoId : MetaAssetType::ImageHash;
    }
}
