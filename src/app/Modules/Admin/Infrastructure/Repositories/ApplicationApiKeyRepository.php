<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Repositories;

use App\Modules\Admin\Application\DTO\ApiKeyFilterDTO;
use App\Modules\Admin\Application\DTO\CreateApiKeyDTO;
use App\Modules\Admin\Domain\Contracts\ApplicationApiKeyRepositoryInterface;
use App\Modules\Admin\Domain\Exceptions\ApiKeyPersistenceFailedException;
use App\Modules\Admin\Infrastructure\Persistence\ApplicationApiKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

readonly class ApplicationApiKeyRepository implements ApplicationApiKeyRepositoryInterface
{
    public function __construct(private ApplicationApiKey $model) {}

    public function findAll(ApiKeyFilterDTO $filters): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['account', 'creator'])
            ->when($filters->accountId, fn (Builder $query, int $accountId) => $query->where('account_id', $accountId))
            ->when(! $filters->includeRevoked, fn (Builder $query) => $query->active())
            ->orderByDesc('id')
            ->paginate(perPage: $filters->perPage, page: $filters->page);
    }

    public function findById(int $id): ?ApplicationApiKey
    {
        return $this->model->newQuery()->with(['account', 'creator'])->find($id);
    }

    public function create(CreateApiKeyDTO $dto, string $prefix, string $tokenHash): ApplicationApiKey
    {
        try {
            return $this->model->newQuery()->create([
                'account_id' => $dto->accountId,
                'name' => $dto->name,
                'prefix' => $prefix,
                'token_hash' => $tokenHash,
                'abilities' => $dto->abilities,
                'created_by_user_id' => $dto->createdByUserId,
            ]);
        } catch (Throwable $exception) {
            // The prefix is the most this context may carry; the token is not in scope here.
            throw ApiKeyPersistenceFailedException::wrap($exception, context: [
                'name' => $dto->name,
                'prefix' => $prefix,
            ]);
        }
    }

    public function revoke(ApplicationApiKey $key): ApplicationApiKey
    {
        try {
            $key->update(['revoked_at' => now()]);

            return $key->refresh();
        } catch (Throwable $exception) {
            throw ApiKeyPersistenceFailedException::wrap($exception, context: ['prefix' => $key->prefix]);
        }
    }
}
