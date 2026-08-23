<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Repositories;

use App\Models\User;
use App\Modules\Auth\Application\DTO\GoogleProfileDTO;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Domain\Exceptions\UserPersistenceFailedException;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(private User $model) {}

    public function findByGoogleId(string $googleId): ?User
    {
        return $this->model->newQuery()->where('google_id', $googleId)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    /**
     * `timezone`, `locale` and `is_active` are left to the column defaults declared in the
     * migration, so the row is re-read: an unwritten default is absent from the new model.
     */
    public function create(GoogleProfileDTO $profile): User
    {
        try {
            return $this->model->newQuery()->create([
                'name' => $profile->name,
                'email' => $profile->email,
                'google_id' => $profile->googleId,
                'avatar_url' => $profile->avatarUrl,
            ])->refresh();
        } catch (Throwable $exception) {
            throw UserPersistenceFailedException::wrap($exception, context: ['email' => $profile->email]);
        }
    }

    /** The name is not overwritten: Google owns the identity, the user owns their display name. */
    public function syncGoogleProfile(User $user, GoogleProfileDTO $profile): User
    {
        try {
            $user->update(array_filter([
                'google_id' => $profile->googleId,
                'avatar_url' => $profile->avatarUrl,
            ], fn (mixed $value): bool => $value !== null));

            return $user->refresh();
        } catch (Throwable $exception) {
            throw UserPersistenceFailedException::wrap($exception, context: ['user_id' => $user->id]);
        }
    }

    public function touchLastLogin(User $user): User
    {
        try {
            $user->update(['last_login_at' => now()]);

            return $user->refresh();
        } catch (Throwable $exception) {
            throw UserPersistenceFailedException::wrap($exception, context: ['user_id' => $user->id]);
        }
    }

    public function issueToken(User $user, string $name): string
    {
        try {
            return $user->createToken($name)->plainTextToken;
        } catch (Throwable $exception) {
            throw UserPersistenceFailedException::wrap($exception, context: ['user_id' => $user->id]);
        }
    }

    /**
     * Only the token that authenticated this request. Revoking every token would sign the
     * same person out of their phone because they signed out of the browser.
     */
    public function revokeCurrentToken(User $user): void
    {
        if (($token = $user->currentAccessToken()) instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function roleNames(User $user): Collection
    {
        return $user->roles()->pluck('name');
    }
}
