<?php

declare(strict_types=1);

namespace App\Modules\Brands\Application\Services;

use App\Modules\Brands\Application\DTO\CreateBrandProfileDTO;
use App\Modules\Brands\Application\DTO\UpdateBrandProfileDTO;
use App\Modules\Brands\Domain\Contracts\BrandProfileRepositoryInterface;
use App\Modules\Brands\Domain\Contracts\BrandProfileUsageProviderInterface;
use App\Modules\Brands\Domain\Exceptions\BrandProfileInUseException;
use App\Modules\Brands\Domain\Exceptions\BrandProfileNotFoundException;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use Illuminate\Support\Collection;

readonly class BrandProfileService
{
    public function __construct(
        private BrandProfileRepositoryInterface $repository,
        private BrandProfileUsageProviderInterface $usage,
    ) {}

    /**
     * @return Collection<int, BrandProfile>
     */
    public function forAccount(int $accountId): Collection
    {
        return $this->repository->findAllForAccount($accountId);
    }

    public function find(int $id, int $accountId): BrandProfile
    {
        return $this->repository->findById($id, $accountId)
            ?? throw BrandProfileNotFoundException::withId($id);
    }

    public function create(CreateBrandProfileDTO $dto): BrandProfile
    {
        return $this->repository->create($dto);
    }

    public function update(UpdateBrandProfileDTO $dto): BrandProfile
    {
        return $this->repository->update(
            $this->find($dto->brandProfileId, $dto->accountId),
            $dto,
        );
    }

    /**
     * A brand profile is the root of every strategy and experiment underneath it, so it is
     * never removed implicitly: the strategies go first, explicitly.
     */
    public function delete(int $id, int $accountId): bool
    {
        return $this->usage->isInUse($id, $accountId)
            ? throw BrandProfileInUseException::withId($id)
            : $this->repository->delete($this->find($id, $accountId));
    }
}
