<?php

declare(strict_types=1);

namespace App\Modules\Brands\Infrastructure\Repositories;

use App\Modules\Brands\Application\DTO\CreateBrandProfileDTO;
use App\Modules\Brands\Application\DTO\UpdateBrandProfileDTO;
use App\Modules\Brands\Domain\Contracts\BrandProfileRepositoryInterface;
use App\Modules\Brands\Domain\Exceptions\BrandProfilePersistenceFailedException;
use App\Modules\Brands\Infrastructure\Persistence\BrandProfile;
use Illuminate\Support\Collection;
use Throwable;

readonly class BrandProfileRepository implements BrandProfileRepositoryInterface
{
    public function __construct(private BrandProfile $model) {}

    public function findAllForAccount(int $accountId): Collection
    {
        return $this->model->newQuery()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id, int $accountId): ?BrandProfile
    {
        return $this->model->newQuery()->where('account_id', $accountId)->find($id);
    }

    public function create(CreateBrandProfileDTO $dto): BrandProfile
    {
        try {
            return $this->model->newQuery()->create(array_filter([
                'account_id' => $dto->accountId,
                'name' => $dto->name,
                'kind' => $dto->kind,
                'description' => $dto->description,
                'niche' => $dto->niche,
                'value_proposition' => $dto->valueProposition,
                'tone_of_voice' => $dto->toneOfVoice,
                'values' => $dto->values,
                'banned_topics' => $dto->bannedTopics,
                'buyer_personas' => $dto->buyerPersonas,
                'reference_competitors' => $dto->referenceCompetitors,
                'brand_colors' => $dto->brandColors,
            ], fn (mixed $value): bool => $value !== null));
        } catch (Throwable $exception) {
            throw BrandProfilePersistenceFailedException::wrap($exception, context: [
                'account_id' => $dto->accountId,
                'name' => $dto->name,
            ]);
        }
    }

    public function update(BrandProfile $brandProfile, UpdateBrandProfileDTO $dto): BrandProfile
    {
        try {
            $brandProfile->update(array_filter([
                'name' => $dto->name,
                'kind' => $dto->kind,
                'description' => $dto->description,
                'niche' => $dto->niche,
                'value_proposition' => $dto->valueProposition,
                'tone_of_voice' => $dto->toneOfVoice,
                'values' => $dto->values,
                'banned_topics' => $dto->bannedTopics,
                'buyer_personas' => $dto->buyerPersonas,
                'reference_competitors' => $dto->referenceCompetitors,
                'brand_colors' => $dto->brandColors,
            ], fn (mixed $value): bool => $value !== null));

            return $brandProfile->refresh();
        } catch (Throwable $exception) {
            throw BrandProfilePersistenceFailedException::wrap($exception, context: [
                'brand_profile_id' => $brandProfile->id,
            ]);
        }
    }

    public function delete(BrandProfile $brandProfile): bool
    {
        try {
            return (bool) $brandProfile->delete();
        } catch (Throwable $exception) {
            throw BrandProfilePersistenceFailedException::wrap($exception, context: [
                'brand_profile_id' => $brandProfile->id,
            ]);
        }
    }
}
