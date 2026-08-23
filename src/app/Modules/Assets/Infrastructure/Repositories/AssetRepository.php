<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Repositories;

use App\Modules\Assets\Application\DTO\AssetFilterDTO;
use App\Modules\Assets\Application\DTO\PersistAssetDTO;
use App\Modules\Assets\Application\DTO\RelocateAssetDTO;
use App\Modules\Assets\Domain\Contracts\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\MetaAssetType;
use App\Modules\Assets\Domain\Exceptions\AssetPersistenceFailedException;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

readonly class AssetRepository implements AssetRepositoryInterface
{
    public function __construct(private Asset $model) {}

    public function findAll(AssetFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $filters->perPage > 0
            ? $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page)
            : $this->query($filters)->get();
    }

    public function findById(int $id, int $accountId): ?Asset
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function findWithSlides(int $id, int $accountId): ?Asset
    {
        return $this->model->newQuery()->with('slides')->where('account_id', $accountId)->find($id);
    }

    public function findMany(array $assetIds, int $accountId): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->whereIn('id', $assetIds)
            ->get();
    }

    public function linkedToDrive(int $accountId): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->whereNotNull('drive_file_id')
            ->where('status', '!=', AssetStatus::Broken->value)
            ->get();
    }

    public function create(PersistAssetDTO $dto): Asset
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'strategy_id' => $dto->strategyId,
                'experiment_id' => $dto->experimentId,
                'parent_asset_id' => $dto->parentAssetId,
                'position' => $dto->position,
                'drive_file_id' => $dto->driveFileId,
                'drive_folder_id' => $dto->driveFolderId,
                'name' => $dto->name,
                'type' => $dto->type,
                'aspect_ratio' => $dto->aspectRatio,
                'duration_seconds' => $dto->durationSeconds,
                'size_bytes' => $dto->sizeBytes,
                'mime_type' => $dto->mimeType,
                'status' => $dto->status,
                'spec_warnings' => $dto->specWarnings,
            ]);
        } catch (Throwable $exception) {
            throw AssetPersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'name' => $dto->name,
            ]);
        }
    }

    public function changeStatus(Asset $asset, AssetStatus $status): Asset
    {
        return $this->persist($asset, ['status' => $status]);
    }

    public function relocate(Asset $asset, RelocateAssetDTO $dto): Asset
    {
        return $this->persist($asset, [
            'experiment_id' => $dto->experimentId,
            'strategy_id' => $dto->strategyId,
            'drive_folder_id' => $dto->driveFolderId,
            'name' => $dto->name,
        ]);
    }

    public function cacheMetaAsset(Asset $asset, string $metaAssetId, MetaAssetType $type): Asset
    {
        return $this->persist($asset, ['meta_asset_id' => $metaAssetId, 'meta_asset_type' => $type]);
    }

    public function delete(Asset $asset): bool
    {
        return (bool) $asset->delete();
    }

    private function persist(Asset $asset, array $attributes): Asset
    {
        try {
            $asset->update($attributes);

            return $asset->refresh();
        } catch (Throwable $exception) {
            throw AssetPersistenceFailedException::wrap($exception, context: ['asset_id' => $asset->id]);
        }
    }

    private function query(AssetFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->where('account_id', $filters->accountId)
            ->when($filters->strategyId, fn (Builder $query, int $id): Builder => $query->where('strategy_id', $id))
            ->when($filters->experimentId, fn (Builder $query, int $id): Builder => $query->where('experiment_id', $id))
            ->when($filters->type, fn (Builder $query, AssetType $type): Builder => $query->where('type', $type->value))
            ->when($filters->status, fn (Builder $query, AssetStatus $status): Builder => $query->where('status', $status->value))
            ->orderByDesc('id');
    }
}
