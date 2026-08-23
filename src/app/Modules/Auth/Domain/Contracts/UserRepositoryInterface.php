<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Contracts;

use App\Models\User;
use App\Modules\Auth\Application\DTO\GoogleProfileDTO;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function findByGoogleId(string $googleId): ?User;

    public function findByEmail(string $email): ?User;

    public function create(GoogleProfileDTO $profile): User;

    public function syncGoogleProfile(User $user, GoogleProfileDTO $profile): User;

    public function touchLastLogin(User $user): User;

    public function issueToken(User $user, string $name): string;

    public function revokeCurrentToken(User $user): void;

    /**
     * @return Collection<int, string>
     */
    public function roleNames(User $user): Collection;
}
