<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Enums;

enum OnboardingStepStatus: string
{
    case PENDING = 'pending';

    case COMPLETED = 'completed';

    case SKIPPED = 'skipped';

    /** A resolved step no longer holds the wizard: skipping is a decision, not a failure. */
    public function isResolved(): bool
    {
        return $this !== self::PENDING;
    }
}
