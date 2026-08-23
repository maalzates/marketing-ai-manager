<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Repositories;

use App\Models\User;
use App\Modules\Admin\Application\DTO\AdminUserFilterDTO;
use App\Modules\Admin\Application\DTO\CreateAdminUserDTO;
use App\Modules\Admin\Domain\Contracts\AdminUserRepositoryInterface;
use App\Modules\Admin\Domain\Exceptions\AdminUserPersistenceFailedException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

readonly class AdminUserRepository implements AdminUserRepositoryInterface
{
    public function __construct(private User $model) {}

    public function findAll(AdminUserFilterDTO $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate(perPage: $filters->perPage, page: $filters->page);
    }

    public function findById(int $id): ?User
    {
        return $this->model->newQuery()->with(['accounts', 'roles'])->find($id);
    }

    /**
     * Pre-provisioning: the row has no google_id until the person signs in with Google for
     * the first time, which is the only way an account ever gets one.
     */
    public function create(CreateAdminUserDTO $dto): User
    {
        try {
            return $this->model->newQuery()->create(['name' => $dto->name, 'email' => $dto->email]);
        } catch (Throwable $exception) {
            throw AdminUserPersistenceFailedException::wrap($exception, context: ['email' => $dto->email]);
        }
    }

    public function update(User $user, ?string $name, ?bool $isActive): User
    {
        try {
            $user->update(array_filter(
                ['name' => $name, 'is_active' => $isActive],
                fn (mixed $value): bool => $value !== null,
            ));

            return $user->refresh();
        } catch (Throwable $exception) {
            throw AdminUserPersistenceFailedException::wrap($exception, context: ['user_id' => $user->id]);
        }
    }

    public function revokeTokens(User $user): void
    {
        try {
            $user->tokens()->delete();
        } catch (Throwable $exception) {
            throw AdminUserPersistenceFailedException::wrap($exception, context: ['user_id' => $user->id]);
        }
    }

    private function query(AdminUserFilterDTO $filters): Builder
    {
        return $this->model->newQuery()
            ->with(['accounts', 'roles'])
            ->when($filters->search, fn (Builder $query, string $search) => $query->where(
                fn (Builder $scoped) => $scoped->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when(
                $filters->isActive !== null,
                fn (Builder $query) => $query->where('is_active', $filters->isActive),
            )
            ->when($filters->role, fn (Builder $query, string $role) => $query->whereHas(
                'roles',
                fn (Builder $scoped) => $scoped->where('name', $role),
            ))
            ->orderBy('id');
    }
}
