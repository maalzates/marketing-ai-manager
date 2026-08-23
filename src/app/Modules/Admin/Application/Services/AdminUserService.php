<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Services;

use App\Models\User;
use App\Modules\Accounts\Application\Services\RoleService;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Admin\Application\DTO\AdminUserDetailDTO;
use App\Modules\Admin\Application\DTO\AdminUserFilterDTO;
use App\Modules\Admin\Application\DTO\CreateAdminUserDTO;
use App\Modules\Admin\Application\DTO\UpdateAdminUserDTO;
use App\Modules\Admin\Application\DTO\UserRoleDTO;
use App\Modules\Admin\Domain\Contracts\AdminUserRepositoryInterface;
use App\Modules\Admin\Domain\Exceptions\AdminUserNotFoundException;
use App\Modules\Audit\Application\DTO\ActionLogFilterDTO;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\DTO\UsageFilterDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Application\Services\UsageService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Audit\Domain\Enums\UsageGrouping;
use App\Modules\Strategies\Application\DTO\StrategyFilterDTO;
use App\Modules\Strategies\Application\Services\StrategyService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

readonly class AdminUserService
{
    private const string ENTITY_TYPE = 'user';

    public function __construct(
        private AdminUserRepositoryInterface $repository,
        private RoleService $roles,
        private StrategyService $strategies,
        private UsageService $usage,
        private ActionLogService $actionLog,
    ) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function findAll(AdminUserFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->findAll($filters)->through($this->overview(...));
    }

    /** @return Collection<string, mixed> */
    public function detail(AdminUserDetailDTO $dto): Collection
    {
        $user = $this->findById($dto->userId);
        $accountId = (int) self::accountIdOf($user);

        return collect([
            'user' => $this->overview($user),
            'strategies' => $this->strategies->forAccount(new StrategyFilterDTO($accountId)),
            'usage' => $this->usage->summary(
                UsageFilterDTO::forAccount($accountId, $dto->from, $dto->to, UsageGrouping::FEATURE),
            ),
            'action_logs' => $this->actionLog->findAll(ActionLogFilterDTO::forAccount(
                $accountId,
                null,
                null,
                $dto->from,
                $dto->to,
                $dto->actionLogPerPage,
                1,
            )),
        ]);
    }

    public function create(CreateAdminUserDTO $dto): User
    {
        $user = $this->repository->create($dto);

        collect($dto->roles)->each(fn (string $role) => $this->roles->assignToUser($role, (int) $user->id));

        $this->record($user, 'admin.user.created', $dto->actorUserId, ['email' => $dto->email]);

        return $this->findById((int) $user->id);
    }

    /**
     * Deactivation is not cosmetic: the open sessions die with it, so the person is out
     * before the next request rather than at the next login.
     */
    public function update(UpdateAdminUserDTO $dto): User
    {
        $user = $this->repository->update($this->findById($dto->userId), $dto->name, $dto->isActive);

        if ($dto->isActive === false) {
            $this->repository->revokeTokens($user);
        }

        $this->record($user, 'admin.user.updated', $dto->actorUserId, ['is_active' => $user->is_active]);

        return $user;
    }

    public function assignRole(UserRoleDTO $dto): User
    {
        $this->roles->assignToUser($dto->role, (int) $this->findById($dto->userId)->id);

        return $this->recorded($dto, 'admin.user.role_assigned');
    }

    public function removeRole(UserRoleDTO $dto): User
    {
        $this->roles->detachFromUser($dto->role, (int) $this->findById($dto->userId)->id);

        return $this->recorded($dto, 'admin.user.role_removed');
    }

    public function findById(int $userId): User
    {
        return $this->repository->findById($userId) ?? throw AdminUserNotFoundException::withId($userId);
    }

    private function recorded(UserRoleDTO $dto, string $action): User
    {
        $user = $this->findById($dto->userId);

        $this->record($user, $action, $dto->actorUserId, ['role' => $dto->role]);

        return $user;
    }

    /**
     * The admin list answers one question per row — who they are, whether they can log in,
     * and what they are spending — so the account and its month-to-date tokens travel with
     * the user rather than in a second call per row from the frontend.
     *
     * @return array<string, mixed>
     */
    private function overview(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'is_active' => $user->is_active,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'roles' => $user->roles->pluck('name'),
            'accounts' => $user->accounts->map(fn (Account $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'is_active' => $account->is_active,
                'sandbox_mode' => $account->sandbox_mode,
                'tokens_this_month' => $this->usage->spentThisMonth((int) $account->id),
            ]),
        ];
    }

    private static function accountIdOf(User $user): ?int
    {
        return $user->accounts->first()?->id;
    }

    /** @param array<string, mixed> $payload */
    private function record(User $user, string $action, int $actorUserId, array $payload): void
    {
        $this->actionLog->record(new RecordActionDTO(
            self::accountIdOf($user),
            $actorUserId,
            $action,
            ActionOrigin::UI,
            $payload,
            self::ENTITY_TYPE,
            (int) $user->id,
        ));
    }
}
