<?php

declare(strict_types=1);

namespace App\Modules\Admin\Domain\Contracts;

use App\Models\User;
use App\Modules\Admin\Application\DTO\AdminUserFilterDTO;
use App\Modules\Admin\Application\DTO\CreateAdminUserDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AdminUserRepositoryInterface
{
    /** @return LengthAwarePaginator<int, User> */
    public function findAll(AdminUserFilterDTO $filters): LengthAwarePaginator;

    public function findById(int $id): ?User;

    public function create(CreateAdminUserDTO $dto): User;

    public function update(User $user, ?string $name, ?bool $isActive): User;

    /** Deactivation has to reach the sessions already open, not only the next login. */
    public function revokeTokens(User $user): void;
}
