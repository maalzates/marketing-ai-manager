<?php

declare(strict_types=1);

namespace App\Modules\Auth;

use App\Modules\Auth\Domain\Contracts\OnboardingStatusInterface;
use App\Modules\Auth\Domain\Contracts\UserRepositoryInterface;
use App\Modules\Auth\Infrastructure\Repositories\UserRepository;
use App\Modules\Auth\Infrastructure\Support\UnknownOnboardingStatus;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);

        // Replaced by the Onboarding module when it lands; until then /auth/me reports "unknown".
        $this->app->bind(OnboardingStatusInterface::class, UnknownOnboardingStatus::class);
    }
}
