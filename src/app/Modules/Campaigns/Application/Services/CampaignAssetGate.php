<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Application\Services;

use App\Modules\Assets\Application\Services\AssetService;
use App\Modules\Assets\Application\Services\MetaAssetUploadService;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use App\Modules\Campaigns\Domain\Enums\AdMediaKind;
use App\Modules\Campaigns\Domain\Exceptions\CampaignWithoutReadyAssetsException;
use App\Modules\Campaigns\Domain\ValueObjects\AdMedia;
use App\Modules\Campaigns\Domain\ValueObjects\MissingAsset;
use Illuminate\Support\Collection;

/**
 * «Piezas primero, campaña después». The gate answers two questions with the same rule:
 * what is still missing (so a proposal can wait for it) and whether a launch may proceed.
 */
readonly class CampaignAssetGate
{
    public function __construct(
        private AssetService $assets,
        private MetaAssetUploadService $uploads,
    ) {}

    /**
     * @param  list<int>  $assetIds
     * @return Collection<int, MissingAsset>
     */
    public function missing(int $accountId, array $assetIds): Collection
    {
        $found = $this->assets->findMany($accountId, $assetIds)->keyBy('id');

        return collect($assetIds)
            ->map(fn (int $assetId): ?MissingAsset => self::gapFor($assetId, $found->get($assetId)))
            ->filter()
            ->values();
    }

    /**
     * @param  list<int>  $assetIds
     *
     * @throws CampaignWithoutReadyAssetsException
     */
    public function assertReady(int $accountId, int $experimentId, array $assetIds): void
    {
        $missing = $this->missing($accountId, $assetIds);

        if ($missing->isNotEmpty()) {
            throw CampaignWithoutReadyAssetsException::missing($experimentId, $missing);
        }
    }

    /**
     * @param  list<int>  $assetIds
     * @return Collection<int, AdMedia>
     *
     * @throws CampaignWithoutReadyAssetsException
     */
    public function uploadedMedia(int $accountId, int $experimentId, array $assetIds): Collection
    {
        $this->assertReady($accountId, $experimentId, $assetIds);

        return collect($assetIds)
            ->map(fn (int $assetId): Asset => $this->uploads->ensureUploaded($accountId, $assetId))
            ->map(self::toAdMedia(...))
            ->values();
    }

    private static function gapFor(int $assetId, ?Asset $asset): ?MissingAsset
    {
        return match (true) {
            $asset === null => new MissingAsset($assetId, 'no existe en la biblioteca de esta cuenta'),
            $asset->status !== AssetStatus::Ready => new MissingAsset(
                $assetId,
                sprintf('está en estado «%s» y no en «%s»', $asset->status->value, AssetStatus::Ready->value),
                $asset->type->value,
                $asset->aspect_ratio,
                $asset->duration_seconds,
            ),
            default => null,
        };
    }

    private static function toAdMedia(Asset $asset): AdMedia
    {
        return new AdMedia(
            $asset->meta_asset_type === MetaAssetType::VideoId ? AdMediaKind::Video : AdMediaKind::Image,
            (string) $asset->meta_asset_id,
        );
    }
}
