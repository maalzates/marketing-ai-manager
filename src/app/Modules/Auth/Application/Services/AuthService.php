<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Models\User;
use App\Modules\Accounts\Application\DTO\CreateAccountDTO;
use App\Modules\Accounts\Application\Services\AccountService;
use App\Modules\Accounts\Application\Services\RoleService;
use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Auth\Application\DTO\AuthenticatedUserDTO;
use App\Modules\Auth\Application\DTO\GoogleProfileDTO;
use App\Modules\Auth\Domain\Contracts\OnboardingStatusInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\GoogleAccountEmailMissingException;
use App\Modules\Auth\Domain\Exceptions\OAuthExchangeFailedException;
use App\Modules\Auth\Domain\Exceptions\UserInactiveException;
use App\Modules\Core\Domain\Support\SecretMasker;
use App\Modules\Integrations\Application\Services\GoogleOAuthService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

readonly class AuthService
{
    private const string LOGIN_ACTION = 'auth.logged_in';

    private const string ENTITY_TYPE = 'user';

    private const string TOKEN_NAME = 'api';

    private const string ROLE_USER = 'user';

    private const string ROLE_ADMIN = 'admin';

    public function __construct(
        private UserRepositoryInterface $users,
        private LoginStateManager $state,
        private GoogleOAuthService $google,
        private AccountService $accounts,
        private RoleService $roles,
        private ActionLogService $actionLog,
        private OnboardingStatusInterface $onboarding,
        private SecretMasker $masker,
        private ConnectionInterface $connection,
    ) {}

    /**
     * @return array{url: string, state: string}
     */
    public function authorisationUrl(): array
    {
        $state = $this->state->issue();

        return [
            'url' => $this->google->authorisationUrl(config('services.google.login_scopes'), $state),
            'state' => $state,
        ];
    }

    public function handleCallback(string $code, string $state): AuthenticatedUserDTO
    {
        $this->state->consume($state);

        return $this->authenticate($this->profileFrom($code));
    }

    /**
     * @return array{user: User, account: ?Account, roles: Collection<int, string>, is_admin: bool, onboarding_completed: ?bool}
     */
    public function me(User $user): array
    {
        $account = $this->accounts->findAllForUser((int) $user->id)->first();
        $roles = $this->users->roleNames($user);

        return [
            'user' => $user,
            'account' => $account,
            'roles' => $roles,
            'is_admin' => $roles->contains(self::ROLE_ADMIN),
            'onboarding_completed' => $account === null
                ? null
                : $this->onboarding->completedFor((int) $account->id),
        ];
    }

    public function logout(User $user): void
    {
        $this->users->revokeCurrentToken($user);
    }

    /** Everything the first login creates lands together or not at all: user, account, roles. */
    private function authenticate(GoogleProfileDTO $profile): AuthenticatedUserDTO
    {
        return $this->connection->transaction(function () use ($profile): AuthenticatedUserDTO {
            $user = $this->provision($profile);
            $account = $this->accountFor($user);

            $this->assignRoles($user);
            $this->actionLog->record(new RecordActionDTO(
                (int) $account->id,
                (int) $user->id,
                self::LOGIN_ACTION,
                ActionOrigin::API,
                ['email' => $user->email],
                self::ENTITY_TYPE,
                (int) $user->id,
            ));

            return new AuthenticatedUserDTO(
                $this->users->issueToken($user, self::TOKEN_NAME),
                $this->users->touchLastLogin($user),
                $account,
            );
        });
    }

    /** Idempotent: a returning user is matched on their Google subject, or adopted by email. */
    private function provision(GoogleProfileDTO $profile): User
    {
        $existing = $this->users->findByGoogleId($profile->googleId)
            ?? $this->users->findByEmail($profile->email);

        return self::ensureActive(
            $existing === null
                ? $this->users->create($profile)
                : $this->users->syncGoogleProfile($existing, $profile)
        );
    }

    private function accountFor(User $user): Account
    {
        return $this->accounts->findAllForUser((int) $user->id)->first()
            ?? $this->accounts->create(new CreateAccountDTO($user->name, (int) $user->id));
    }

    private function assignRoles(User $user): void
    {
        $this->roles->assignToUser(self::ROLE_USER, (int) $user->id);

        if (self::isAdministrator($user->email)) {
            $this->roles->assignToUser(self::ROLE_ADMIN, (int) $user->id);
        }
    }

    private function profileFrom(string $code): GoogleProfileDTO
    {
        $tokens = $this->google->exchangeCode($code);

        return $tokens->accessToken === ''
            ? throw OAuthExchangeFailedException::withDiagnosis($this->masker->mask([
                'reason' => 'empty_access_token',
                'scopes' => $tokens->scopes,
            ]))
            : $this->toProfile($this->google->userInfo($tokens->accessToken));
    }

    /**
     * @param  array<string, mixed>  $userInfo
     */
    private function toProfile(array $userInfo): GoogleProfileDTO
    {
        return new GoogleProfileDTO(
            (string) ($userInfo['sub'] ?? throw OAuthExchangeFailedException::withDiagnosis(
                $this->masker->mask(['reason' => 'missing_subject', 'fields' => array_keys($userInfo)])
            )),
            (string) ($userInfo['email'] ?? throw GoogleAccountEmailMissingException::forSubject(
                (string) $userInfo['sub']
            )),
            (string) ($userInfo['name'] ?? $userInfo['email']),
            isset($userInfo['picture']) ? (string) $userInfo['picture'] : null,
        );
    }

    private static function ensureActive(User $user): User
    {
        return $user->is_active ? $user : throw UserInactiveException::withId((int) $user->id);
    }

    /** Case-insensitive: an email is not case-sensitive, and a capitalised one losing admin is a bug. */
    private static function isAdministrator(string $email): bool
    {
        return in_array(
            strtolower($email),
            array_map(strtolower(...), config('accounts.admin_emails')),
            true,
        );
    }
}
