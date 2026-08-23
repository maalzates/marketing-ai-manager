<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Support;

use App\Modules\Auth\Domain\Contracts\OnboardingStatusInterface;

readonly class UnknownOnboardingStatus implements OnboardingStatusInterface
{
    public function completedFor(int $accountId): ?bool
    {
        return null;
    }
}
