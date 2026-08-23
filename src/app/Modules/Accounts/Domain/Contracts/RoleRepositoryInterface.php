<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Domain\Contracts;

use App\Modules\Accounts\Application\DTO\CreateRoleDTO;
use App\Modules\Accounts\Application\DTO\UpdateRoleDTO;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /**
     * @return Collection<int, Role>
     */
    public function findAll(): Collection;

    public function findById(int $id): ?Role;

    public function findByName(string $name): ?Role;

    public function create(CreateRoleDTO $dto): Role;

    public function update(Role $role, UpdateRoleDTO $dto): Role;

    public function delete(Role $role): bool;

    public function isAssigned(Role $role): bool;

    public function attachToUser(Role $role, int $userId): Role;

    public function detachFromUser(Role $role, int $userId): Role;
}
