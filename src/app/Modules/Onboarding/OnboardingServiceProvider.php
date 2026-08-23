<?php

declare(strict_types=1);

namespace App\Modules\Onboarding;

use App\Modules\Onboarding\Domain\Contracts\OnboardingStateRepositoryInterface;
use App\Modules\Onboarding\Infrastructure\Repositories\OnboardingStateRepository;
use Illuminate\Support\ServiceProvider;

class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OnboardingStateRepositoryInterface::class, OnboardingStateRepository::class);
    }
}
