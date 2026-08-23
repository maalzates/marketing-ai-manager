<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Contracts;

use App\Modules\Assets\Application\DTO\AssetFilterDTO;
use App\Modules\Assets\Application\DTO\PersistAssetDTO;
use App\Modules\Assets\Application\DTO\RelocateAssetDTO;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AssetRepositoryInterface
{
    public function findAll(AssetFilterDTO $filters): Collection|LengthAwarePaginator;

    public function findById(int $id, int $accountId): ?Asset;

    public function findWithSlides(int $id, int $accountId): ?Asset;

    /**
     * @param  list<int>  $assetIds
     * @return Collection<int, Asset>
     */
    public function findMany(array $assetIds, int $accountId): Collection;

    /** @return Collection<int, Asset> */
    public function linkedToDrive(int $accountId): Collection;

    public function create(PersistAssetDTO $dto): Asset;

    public function changeStatus(Asset $asset, AssetStatus $status): Asset;

    public function relocate(Asset $asset, RelocateAssetDTO $dto): Asset;

    public function cacheMetaAsset(Asset $asset, string $metaAssetId, MetaAssetType $type): Asset;

    public function delete(Asset $asset): bool;
}
