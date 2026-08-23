<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Application\DTO\AssetFilterDTO;
use App\Modules\Assets\Application\DTO\AttachToExperimentDTO;
use App\Modules\Assets\Application\DTO\CreateCarouselDTO;
use App\Modules\Assets\Application\DTO\DriveFileDTO;
use App\Modules\Assets\Application\DTO\LinkExistingAssetDTO;
use App\Modules\Assets\Application\DTO\PersistAssetDTO;
use App\Modules\Assets\Application\DTO\RelocateAssetDTO;
use App\Modules\Assets\Application\DTO\UploadAssetDTO;
use App\Modules\Assets\Application\DTO\UploadedSourceDTO;
use App\Modules\Assets\Domain\Contracts\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Contracts\DriveClientFactoryInterface;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Exceptions\AssetNotFoundException;
use App\Modules\Assets\Domain\Exceptions\AssetPlacementUndeterminedException;
use App\Modules\Assets\Domain\Exceptions\AssetWithoutDriveFileException;
use App\Modules\Assets\Domain\ValueObjects\AssetLocation;
use App\Modules\Assets\Domain\ValueObjects\AssetPlacement;
use App\Modules\Assets\Domain\ValueObjects\MediaFilename;
use App\Modules\Assets\Infrastructure\Persistence\Asset;
use App\Modules\Brands\Application\Services\BrandProfileService;
use App\Modules\Experiments\Application\Services\ExperimentService;
use App\Modules\Strategies\Application\Services\StrategyService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Drive stores, the database governs. Every method here writes the row that owns the truth and
 * treats the Drive file as a side effect addressed only by `drive_file_id`.
 */
readonly class AssetService
{
    private const string EXPERIMENT_FOLDER_FORMAT = '%s – %s';

    public function __construct(
        private AssetRepositoryInterface $repository,
        private DriveClientFactoryInterface $clients,
        private DriveFolderService $folders,
        private AssetNamingService $naming,
        private AssetSpecValidator $specs,
        private BrandProfileService $brands,
        private StrategyService $strategies,
        private ExperimentService $experiments,
    ) {}

    public function findAll(AssetFilterDTO $filters): Collection|LengthAwarePaginator
    {
        return $this->repository->findAll($filters);
    }

    public function find(int $id, int $accountId): Asset
    {
        return $this->repository->findWithSlides($id, $accountId) ?? throw AssetNotFoundException::withId($id);
    }

    /**
     * Returns only the ids that exist, so a caller gating on assets can report a missing one as
     * missing rather than as a 404.
     *
     * @param  list<int>  $assetIds
     * @return Collection<int, Asset>
     */
    public function findMany(int $accountId, array $assetIds): Collection
    {
        return $this->repository->findMany($assetIds, $accountId);
    }

    public function upload(UploadAssetDTO $dto): Asset
    {
        $placement = $this->placementFor($dto->accountId, $dto->strategyId, $dto->experimentId);

        return $this->persist(
            $dto->accountId,
            $dto->type,
            $placement,
            $this->push(
                $dto->accountId,
                $this->uploadName($placement, $dto),
                $this->folders->folderFor($dto->accountId, $placement->location),
                $dto->source,
            ),
            $dto->source,
        );
    }

    /** Registers a file the user already owns; it is never moved, so Drive keeps deciding where it lives. */
    public function linkExisting(LinkExistingAssetDTO $dto): Asset
    {
        $placement = $this->placementFor($dto->accountId, $dto->strategyId, $dto->experimentId);

        return $this->persist(
            $dto->accountId,
            $dto->type,
            $placement,
            DriveFileDTO::fromResponse($this->clients->forAccount($dto->accountId)->metadata($dto->driveFileId)),
        );
    }

    /** Moves the piece out of `_inbox/` into the experiment folder and renames it to the convention. */
    public function attachToExperiment(AttachToExperimentDTO $dto): Asset
    {
        $asset = $this->find($dto->assetId, $dto->accountId);
        $placement = $this->placementFor($dto->accountId, null, $dto->experimentId);
        $folderId = $this->folders->folderFor($dto->accountId, $placement->location);
        $name = $this->naming->forSingle(
            (string) $placement->experimentCode,
            $asset->type,
            $dto->topic ?? $asset->name,
            $dto->version,
            (new MediaFilename($asset->name, $asset->mime_type))->extension(),
        );

        $this->relocateInDrive($dto->accountId, $asset, $folderId, $name);

        return $this->repository->relocate($asset, new RelocateAssetDTO($dto->experimentId, $placement->strategyId, $folderId, $name));
    }

    public function markReady(int $id, int $accountId): Asset
    {
        return $this->repository->changeStatus($this->find($id, $accountId), AssetStatus::Ready);
    }

    public function markUsed(int $id, int $accountId): Asset
    {
        return $this->repository->changeStatus($this->find($id, $accountId), AssetStatus::Used);
    }

    public function archive(int $id, int $accountId): Asset
    {
        return $this->repository->changeStatus($this->find($id, $accountId), AssetStatus::Archived);
    }

    /**
     * Sweeps the account's linked files and marks as broken every one Drive no longer serves, so
     * the UI can offer a re-link instead of failing at publish time.
     *
     * @return Collection<int, Asset>
     */
    public function detectBroken(int $accountId): Collection
    {
        $client = $this->clients->forAccount($accountId);

        return $this->repository->linkedToDrive($accountId)
            ->filter(fn (Asset $asset): bool => $client->trashCheck((string) $asset->drive_file_id))
            ->map(fn (Asset $asset): Asset => $this->repository->changeStatus($asset, AssetStatus::Broken))
            ->values();
    }

    /** The parent holds no bytes: it exists so the slide order lives in the database. */
    public function carousel(CreateCarouselDTO $dto): Asset
    {
        $placement = $this->placementFor($dto->accountId, $dto->strategyId, $dto->experimentId);
        $folderId = $this->folders->folderFor($dto->accountId, $placement->location);
        $parent = $this->repository->create(new PersistAssetDTO(
            $dto->accountId,
            AssetType::Carousel,
            Str::slug($dto->topic),
            $placement->strategyId,
            $placement->experimentId,
            driveFolderId: $folderId,
        ));

        foreach ($dto->slides as $index => $slide) {
            $this->createSlide($dto, $placement, $folderId, $parent->id, $slide, $index + 1);
        }

        return $this->find($parent->id, $dto->accountId);
    }

    /** Only the row goes: the file stays in the user's Drive, which this application does not own. */
    public function delete(int $id, int $accountId): bool
    {
        return $this->repository->delete($this->find($id, $accountId));
    }

    private function createSlide(
        CreateCarouselDTO $dto,
        AssetPlacement $placement,
        string $folderId,
        int $parentId,
        UploadedSourceDTO $slide,
        int $position,
    ): void {
        $file = $this->push($dto->accountId, $this->slideName($placement, $dto->topic, $slide, $position), $folderId, $slide);

        $this->repository->create(new PersistAssetDTO(
            $dto->accountId,
            AssetType::CarouselSlide,
            $file->name,
            $placement->strategyId,
            $placement->experimentId,
            $parentId,
            $position,
            $file->id,
            $file->folderId,
            $file->aspectRatio?->label(),
            $file->durationSeconds,
            $file->sizeBytes ?? $slide->sizeBytes,
            $file->mimeType ?? $slide->mimeType,
            specWarnings: $this->specs->warningsFor(AssetType::CarouselSlide, $file),
        ));
    }

    private function persist(
        int $accountId,
        AssetType $type,
        AssetPlacement $placement,
        DriveFileDTO $file,
        ?UploadedSourceDTO $source = null,
    ): Asset {
        return $this->repository->create(new PersistAssetDTO(
            $accountId,
            $type,
            $file->name,
            $placement->strategyId,
            $placement->experimentId,
            driveFileId: $file->id,
            driveFolderId: $file->folderId,
            aspectRatio: $file->aspectRatio?->label(),
            durationSeconds: $file->durationSeconds,
            sizeBytes: $file->sizeBytes ?? $source?->sizeBytes,
            mimeType: $file->mimeType ?? $source?->mimeType,
            specWarnings: $this->specs->warningsFor($type, $file),
        ));
    }

    /** Above the configured threshold Drive rejects a single request, which for video is always. */
    private function push(int $accountId, string $name, string $folderId, UploadedSourceDTO $source): DriveFileDTO
    {
        return DriveFileDTO::fromResponse(
            $source->sizeBytes > (int) config('services.google.resumable_upload_threshold_bytes')
                ? $this->clients->forAccount($accountId)->uploadResumable($name, $folderId, $source->mimeType, $source->path, $source->sizeBytes)
                : $this->clients->forAccount($accountId)->uploadSimple($name, $folderId, $source->mimeType, $source->path)
        );
    }

    private function relocateInDrive(int $accountId, Asset $asset, string $folderId, string $name): void
    {
        $client = $this->clients->forAccount($accountId);
        $fileId = $asset->drive_file_id ?? throw AssetWithoutDriveFileException::withId($asset->id);

        $client->move($fileId, $folderId, (string) $asset->drive_folder_id);
        $client->rename($fileId, $name);
    }

    private function uploadName(AssetPlacement $placement, UploadAssetDTO $dto): string
    {
        return $placement->experimentCode === null
            ? $dto->source->originalName
            : $this->naming->forSingle(
                $placement->experimentCode,
                $dto->type,
                $dto->topic ?? $dto->source->originalName,
                $dto->version,
                $dto->source->extension(),
            );
    }

    private function slideName(AssetPlacement $placement, string $topic, UploadedSourceDTO $slide, int $position): string
    {
        return $placement->experimentCode === null
            ? $slide->originalName
            : $this->naming->forSlide($placement->experimentCode, $topic, $position, $slide->extension());
    }

    private function placementFor(int $accountId, ?int $strategyId, ?int $experimentId): AssetPlacement
    {
        return $experimentId === null
            ? $this->inboxPlacement($accountId, $strategyId)
            : $this->experimentPlacement($accountId, $experimentId);
    }

    private function experimentPlacement(int $accountId, int $experimentId): AssetPlacement
    {
        $experiment = $this->experiments->find($experimentId, $accountId);
        $strategy = $this->strategies->find($experiment->strategy_id, $accountId);

        return new AssetPlacement(
            new AssetLocation(
                $this->brands->find($strategy->brand_profile_id, $accountId)->name,
                $strategy->name,
                $experiment->code,
                sprintf(self::EXPERIMENT_FOLDER_FORMAT, $experiment->code, self::folderSafe($experiment->title)),
            ),
            $strategy->id,
            $experiment->id,
            $experiment->code,
        );
    }

    /** The title is free text a user typed; a slash or a newline in it makes a Drive folder unusable. */
    private static function folderSafe(string $title): string
    {
        return Str::limit(trim((string) preg_replace('/[\\\\\/\x00-\x1f]+/', ' ', $title)), 120, '');
    }

    private function inboxPlacement(int $accountId, ?int $strategyId): AssetPlacement
    {
        $strategy = $this->strategies->find(
            $strategyId ?? throw AssetPlacementUndeterminedException::forAccount($accountId),
            $accountId,
        );

        return new AssetPlacement(
            new AssetLocation($this->brands->find($strategy->brand_profile_id, $accountId)->name),
            $strategy->id,
            null,
            null,
        );
    }
}
