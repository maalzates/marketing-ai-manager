<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Exceptions;

use App\Modules\Core\Domain\Exceptions\ClientException;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;
use Symfony\Component\HttpFoundation\Response;

/**
 * A step only completes on a live call that the provider answered. When it did not, the
 * user needs the probable cause and the way out, not a red cross — so the guide travels
 * back with the failure and the wizard can reopen it on the spot.
 */
class OnboardingStepVerificationFailedException extends ClientException
{
    public static function forStep(OnboardingStep $step, Integration $integration, ?string $guideUrl): self
    {
        $exception = new self(self::message($step, $integration), Response::HTTP_UNPROCESSABLE_ENTITY);

        $exception->context = [
            'step' => $step->value,
            'provider' => $integration->provider->value,
            'status' => $integration->status->value,
            'last_error' => $integration->last_error,
        ];

        $exception->extras = [
            'step' => $step->value,
            'provider' => $integration->provider->value,
            'integration_status' => $integration->status->value,
            'guide_url' => $guideUrl,
        ];

        return $exception;
    }

    private static function message(OnboardingStep $step, Integration $integration): string
    {
        return match ($integration->status) {
            IntegrationStatus::EXPIRED => "La autorización de {$integration->provider->label()} ya no es válida. Vuelve a conectarla desde el paso «{$step->label()}»; no se recupera reintentando.",
            default => "{$integration->provider->label()} rechazó la comprobación. Revisa la credencial siguiendo la guía del paso «{$step->label()}» y vuelve a guardarla.",
        };
    }
}
