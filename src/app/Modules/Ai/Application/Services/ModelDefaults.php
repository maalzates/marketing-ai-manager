<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

/**
 * The models an account should route to once it connects a provider. Without this, a new
 * account keeps the registry's defaults — all of them Anthropic — and every AI feature
 * fails on the first call with "you have not connected Anthropic yet".
 */
readonly class ModelDefaults
{
    /** @return array<string, string> settings key => model */
    public function forProvider(IntegrationProvider $provider): array
    {
        $llm = LlmProvider::tryFrom($provider->value);

        return $llm === null ? [] : collect(AiTask::cases())
            ->mapWithKeys(static fn (AiTask $task): array => [
                $task->settingKey() => $task->prefersCapableModel()
                    ? $llm->capableModel()
                    : $llm->cheapestModel(),
            ])
            ->all();
    }
}
