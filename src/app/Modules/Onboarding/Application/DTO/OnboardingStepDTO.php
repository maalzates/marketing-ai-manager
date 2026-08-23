<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\DTO;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;

readonly class OnboardingStepDTO
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public OnboardingStep $step,
        public ?IntegrationProvider $provider = null,
    ) {}
}
