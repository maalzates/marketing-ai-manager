<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Application\Services;

use App\Modules\Accounts\Application\DTO\CreateRoleDTO;
use App\Modules\Accounts\Application\DTO\UpdateRoleDTO;
use App\Modules\Accounts\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Accounts\Domain\Exceptions\RoleInUseException;
use App\Modules\Accounts\Domain\Exceptions\RoleNotFoundException;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Support\Collection;

readonly class RoleService
{
    public function __construct(private RoleRepositoryInterface $repository) {}

    /**
     * @return Collection<int, Role>
     */
    public function findAll(): Collection
    {
        return $this->repository->findAll();
    }

    public function findById(int $id): Role
    {
        return $this->repository->findById($id) ?? throw RoleNotFoundException::withId($id);
    }

    public function findByName(string $name): Role
    {
        return $this->repository->findByName($name) ?? throw RoleNotFoundException::withName($name);
    }

    public function create(CreateRoleDTO $dto): Role
    {
        return $this->repository->create($dto);
    }

    public function update(UpdateRoleDTO $dto): Role
    {
        return $this->repository->update($this->findById($dto->roleId), $dto);
    }

    /** A role still held by a user cannot be removed: it would silently drop their access. */
    public function delete(int $id): bool
    {
        $role = $this->findById($id);

        return $this->repository->isAssigned($role)
            ? throw RoleInUseException::withId($id)
            : $this->repository->delete($role);
    }

    public function assignToUser(string $name, int $userId): Role
    {
        return $this->repository->attachToUser($this->findByName($name), $userId);
    }

    public function detachFromUser(string $name, int $userId): Role
    {
        return $this->repository->detachFromUser($this->findByName($name), $userId);
    }
}
