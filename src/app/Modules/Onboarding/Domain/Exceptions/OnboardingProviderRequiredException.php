<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;
use Symfony\Component\HttpFoundation\Response;

class OnboardingProviderRequiredException extends ClientException
{
    public static function forStep(OnboardingStep $step): self
    {
        $exception = new self(
            "El paso «{$step->label()}» admite varios proveedores: indica cuál quieres verificar.",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['step' => $step->value];
        $exception->extras = ['valid_providers' => $step->providerValues()];

        return $exception;
    }

    public static function notPartOfStep(OnboardingStep $step, IntegrationProvider $provider): self
    {
        $exception = new self(
            "{$provider->label()} no pertenece al paso «{$step->label()}».",
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );

        $exception->context = ['step' => $step->value, 'provider' => $provider->value];

        return $exception;
    }
}
