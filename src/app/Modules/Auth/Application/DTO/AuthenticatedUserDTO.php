<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\DTO;

use App\Models\User;
use App\Modules\Accounts\Infrastructure\Persistence\Account;

/**
 * The plaintext token exists only here, on the way out of the callback response. Sanctum
 * keeps a hash, so this value can never be recovered afterwards and must never be stored.
 */
readonly class AuthenticatedUserDTO
{
    public function __construct(
        public string $token,
        public User $user,
        public Account $account,
    ) {}
}
