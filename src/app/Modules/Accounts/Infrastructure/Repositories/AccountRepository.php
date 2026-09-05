<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Infrastructure\Repositories;

use App\Modules\Accounts\Application\DTO\AccountFilterDTO;
use App\Modules\Accounts\Application\DTO\CreateAccountDTO;
use App\Modules\Accounts\Application\DTO\UpdateAccountDTO;
use App\Modules\Accounts\Domain\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Domain\Exceptions\AccountPersistenceFailedException;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Illuminate\Support\Collection;
use Throwable;

readonly class AccountRepository implements AccountRepositoryInterface
{
    public function __construct(private Account $model) {}

    public function find(AccountFilterDTO $filters): ?Account
    {
        return $this->model->newQuery()->find($filters->accountId);
    }

    public function findAllActive(): Collection
    {
        return $this->model->newQuery()->where('is_active', true)->orderBy('id')->get();
    }

    public function findAllForUser(int $userId): Collection
    {
        return $this->model->newQuery()
            ->whereRelation('users', 'users.id', $userId)
            ->get();
    }

    public function create(CreateAccountDTO $dto): Account
    {
        try {
            return $this->model->newQuery()->create(array_filter([
                'name' => $dto->name,
                'owner_user_id' => $dto->ownerUserId,
                'currency' => $dto->currency,
                'timezone' => $dto->timezone,
            ], fn (mixed $value): bool => $value !== null));
        } catch (Throwable $exception) {
            throw AccountPersistenceFailedException::wrap($exception, context: ['owner_user_id' => $dto->ownerUserId]);
        }
    }

    public function update(Account $account, UpdateAccountDTO $dto): Account
    {
        try {
            $account->update(['currency' => $dto->currency]);

            return $account->refresh();
        } catch (Throwable $exception) {
            throw AccountPersistenceFailedException::wrap($exception, context: ['account_id' => $account->id]);
        }
    }

    public function attachUser(Account $account, int $userId): Account
    {
        try {
            $account->users()->syncWithoutDetaching([$userId]);

            return $account->refresh();
        } catch (Throwable $exception) {
            throw AccountPersistenceFailedException::wrap($exception, context: [
                'account_id' => $account->id,
                'user_id' => $userId,
            ]);
        }
    }

    public function setActive(Account $account, bool $isActive): Account
    {
        try {
            $account->update(['is_active' => $isActive]);

            return $account->refresh();
        } catch (Throwable $exception) {
            throw AccountPersistenceFailedException::wrap($exception, context: ['account_id' => $account->id]);
        }
    }
}
