<?php

declare(strict_types=1);

namespace App\Modules\Brands\Domain\Contracts;

use App\Modules\Brands\Application\DTO\CreateBrandProfileDTO;
use App\Modules\Brands\Application\DTO\UpdateBrandProfileDTO;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use Illuminate\Support\Collection;

interface BrandProfileRepositoryInterface
{
    /**
     * @return Collection<int, BrandProfile>
     */
    public function findAllForAccount(int $accountId): Collection;

    public function findById(int $id, int $accountId): ?BrandProfile;

    public function create(CreateBrandProfileDTO $dto): BrandProfile;

    public function update(BrandProfile $brandProfile, UpdateBrandProfileDTO $dto): BrandProfile;

    public function delete(BrandProfile $brandProfile): bool;
}
