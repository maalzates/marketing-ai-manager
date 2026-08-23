<?php

declare(strict_types=1);

namespace App\Modules\Accounts\Infrastructure\Repositories;

use App\Modules\Accounts\Application\DTO\CreateRoleDTO;
use App\Modules\Accounts\Application\DTO\UpdateRoleDTO;
use App\Modules\Accounts\Domain\Contracts\RoleRepositoryInterface;
use App\Modules\Accounts\Domain\Exceptions\RolePersistenceFailedException;
use App\Modules\Accounts\Infrastructure\Persistence\Role;
use Illuminate\Support\Collection;
use Throwable;

readonly class RoleRepository implements RoleRepositoryInterface
{
    public function __construct(private Role $model) {}

    public function findAll(): Collection
    {
        return $this->model->newQuery()->orderBy('name')->get();
    }

    public function findById(int $id): ?Role
    {
        return $this->model->newQuery()->find($id);
    }

    public function findByName(string $name): ?Role
    {
        return $this->model->newQuery()->where('name', $name)->first();
    }

    public function create(CreateRoleDTO $dto): Role
    {
        try {
            return $this->model->newQuery()->create(['name' => $dto->name, 'label' => $dto->label]);
        } catch (Throwable $exception) {
            throw RolePersistenceFailedException::wrap($exception, context: ['role' => $dto->name]);
        }
    }

    public function update(Role $role, UpdateRoleDTO $dto): Role
    {
        try {
            $role->update(array_filter([
                'name' => $dto->name,
                'label' => $dto->label,
            ], fn (mixed $value): bool => $value !== null));

            return $role->refresh();
        } catch (Throwable $exception) {
            throw RolePersistenceFailedException::wrap($exception, context: ['role_id' => $role->id]);
        }
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }

    public function isAssigned(Role $role): bool
    {
        return $role->users()->exists();
    }

    public function attachToUser(Role $role, int $userId): Role
    {
        try {
            $role->users()->syncWithoutDetaching([$userId]);

            return $role->refresh();
        } catch (Throwable $exception) {
            throw RolePersistenceFailedException::wrap($exception, context: [
                'role_id' => $role->id,
                'user_id' => $userId,
            ]);
        }
    }

    public function detachFromUser(Role $role, int $userId): Role
    {
        try {
            $role->users()->detach($userId);

            return $role->refresh();
        } catch (Throwable $exception) {
            throw RolePersistenceFailedException::wrap($exception, context: [
                'role_id' => $role->id,
                'user_id' => $userId,
            ]);
        }
    }
}
