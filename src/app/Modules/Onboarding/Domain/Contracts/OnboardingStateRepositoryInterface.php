<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Contracts;

use App\Modules\Onboarding\Infrastructure\Persistence\OnboardingState;

interface OnboardingStateRepositoryInterface
{
    /** @param array<string, mixed> $steps the fresh map used only when the row does not exist yet */
    public function firstOrCreateForAccount(int $accountId, array $steps): OnboardingState;

    /** @param array<string, mixed> $steps */
    public function replaceSteps(OnboardingState $state, array $steps): OnboardingState;

    public function markCompleted(OnboardingState $state): OnboardingState;
}
