<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ApiException;

class OnboardingStateWriteFailedException extends ApiException {}
