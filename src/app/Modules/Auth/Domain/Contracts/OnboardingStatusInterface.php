<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Contracts;

/**
 * `null` means "not known", which is what the SPA gets while the Onboarding module has not
 * landed yet. Auth declares the port; Onboarding rebinds it when it ships.
 */
interface OnboardingStatusInterface
{
    public function completedFor(int $accountId): ?bool;
}
