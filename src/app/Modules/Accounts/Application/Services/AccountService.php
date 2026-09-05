<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\Services;

use App\Modules\Accounts\Application\DTO\AccountFilterDTO;
use App\Modules\Accounts\Application\DTO\CreateAccountDTO;
use App\Modules\Accounts\Application\DTO\UpdateAccountDTO;
use App\Modules\Accounts\Domain\Contracts\AccountRepositoryInterface;
use App\Modules\Accounts\Domain\Exceptions\AccountInactiveException;
use App\Modules\Accounts\Domain\Exceptions\AccountNotFoundException;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Illuminate\Support\Collection;

readonly class AccountService
{
    public function __construct(private AccountRepositoryInterface $repository) {}

    public function findById(AccountFilterDTO $filters): Account
    {
        return $this->repository->find($filters) ?? throw AccountNotFoundException::withId($filters->accountId);
    }

    public function findActiveById(AccountFilterDTO $filters): Account
    {
        $account = $this->findById($filters);

        return $account->is_active ? $account : throw AccountInactiveException::withId($filters->accountId);
    }

    /**
     * @return Collection<int, Account>
     */
    public function findAllActive(): Collection
    {
        return $this->repository->findAllActive();
    }

    /**
     * @return Collection<int, Account>
     */
    public function findAllForUser(int $userId): Collection
    {
        return $this->repository->findAllForUser($userId);
    }

    /** The owner is always a member: an account nobody belongs to is unreachable. */
    public function create(CreateAccountDTO $dto): Account
    {
        return $this->repository->attachUser($this->repository->create($dto), $dto->ownerUserId);
    }

    public function update(UpdateAccountDTO $dto): Account
    {
        return $this->repository->update(
            $this->findActiveById(new AccountFilterDTO($dto->accountId)),
            $dto,
        );
    }

    public function activate(AccountFilterDTO $filters): Account
    {
        return $this->repository->setActive($this->findById($filters), true);
    }

    public function deactivate(AccountFilterDTO $filters): Account
    {
        return $this->repository->setActive($this->findById($filters), false);
    }
}
