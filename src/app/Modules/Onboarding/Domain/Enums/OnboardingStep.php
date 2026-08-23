<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Enums;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * The four core steps of the wizard, in the order core.md §8 fixes them. TikTok, YouTube
 * and any later channel are deliberately absent: they are optional connectors that live
 * in Configuración → Integraciones, and adding one here would make a first login longer
 * for a feature most accounts never use.
 */
enum OnboardingStep: string
{
    case LLM = 'llm';

    case APIFY = 'apify';

    case META = 'meta';

    case GOOGLE = 'google';

    /**
     * Any one of these providers completes the step: an account needs a single LLM, not
     * all three.
     *
     * @return list<IntegrationProvider>
     */
    public function providers(): array
    {
        return match ($this) {
            self::LLM => [IntegrationProvider::ANTHROPIC, IntegrationProvider::OPENAI, IntegrationProvider::GEMINI],
            self::APIFY => [IntegrationProvider::APIFY],
            self::META => [IntegrationProvider::META],
            self::GOOGLE => [IntegrationProvider::GOOGLE],
        };
    }

    /** @return list<string> */
    public function providerValues(): array
    {
        return array_map(fn (IntegrationProvider $provider): string => $provider->value, $this->providers());
    }

    /** @return list<string> keys of the `onboarding_guide` entries the Knowledge module serves */
    public function guideKeys(): array
    {
        return match ($this) {
            self::LLM => ['llm-anthropic', 'llm-openai', 'llm-gemini'],
            self::APIFY => ['apify'],
            self::META => ['meta'],
            self::GOOGLE => ['google'],
        };
    }

    /** What stays locked in the dashboard while the step is unresolved. */
    public function unlocks(): string
    {
        return match ($this) {
            self::LLM => 'ai',
            self::APIFY => 'competitor_analysis',
            self::META => 'campaigns',
            self::GOOGLE => 'assets',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LLM => 'Modelo de lenguaje',
            self::APIFY => 'Apify',
            self::META => 'Meta (Instagram y Facebook Ads)',
            self::GOOGLE => 'Google (Drive y YouTube)',
        };
    }

    /** @return list<self> */
    public static function ordered(): array
    {
        return self::cases();
    }
}
