<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Contracts;

use App\Modules\Accounts\Application\DTO\AccountFilterDTO;
use App\Modules\Accounts\Application\DTO\CreateAccountDTO;
use App\Modules\Accounts\Application\DTO\UpdateAccountDTO;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use Illuminate\Support\Collection;

interface AccountRepositoryInterface
{
    public function find(AccountFilterDTO $filters): ?Account;

    /**
     * @return Collection<int, Account>
     */
    public function findAllActive(): Collection;

    /**
     * @return Collection<int, Account>
     */
    public function findAllForUser(int $userId): Collection;

    public function create(CreateAccountDTO $dto): Account;

    public function update(Account $account, UpdateAccountDTO $dto): Account;

    public function attachUser(Account $account, int $userId): Account;

    public function setActive(Account $account, bool $isActive): Account;
}
