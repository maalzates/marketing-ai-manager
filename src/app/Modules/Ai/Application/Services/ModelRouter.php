<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Contracts\ModelCatalogRepositoryInterface;
use App\Modules\Ai\Domain\Enums\AiTask;
use App\Modules\Ai\Domain\Enums\LlmProvider;
use App\Modules\Ai\Domain\Exceptions\UnknownAiTaskException;
use App\Modules\Ai\Domain\Exceptions\UnknownLlmModelException;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Settings\Application\Services\SettingsService;
use Illuminate\Support\Collection;

/**
 * Turns "what am I doing" into "which model answers it", per account. Mechanical tasks can
 * ride a cheap model while judgement tasks ride a capable one, which is the whole point of
 * the per-task selector in Settings → Models.
 */
readonly class ModelRouter
{
    private const string SAME_FOR_ALL_KEY = 'ai.models.same_for_all';

    public function __construct(
        private SettingsService $settings,
        private IntegrationService $integrations,
        private ModelCatalogRepositoryInterface $catalog,
    ) {}

    public function modelFor(AiTask $task, int $accountId): string
    {
        $key = $this->effectiveTask($task, $accountId)->settingKey();
        $model = (string) ($this->settings->get($key, $accountId) ?? throw UnknownAiTaskException::withoutModel($task, $key));

        return $this->reachable($model, $task, $accountId);
    }

    /**
     * A model whose provider the account never connected would fail far from here, inside
     * `CredentialResolver`, with a message naming a provider the user did not choose. So the
     * routing swaps it for the same tier on a provider that *is* connected.
     *
     * With no LLM connected at all there is nothing to swap to: the configured model is
     * returned untouched and the credential error downstream is the right answer.
     */
    private function reachable(string $model, AiTask $task, int $accountId): string
    {
        $connected = $this->connectedProviders($accountId);

        if ($connected->isEmpty() || $connected->contains($this->providerFor($model, $accountId))) {
            return $model;
        }

        // The tier comes from the task, not from the model's id. Matching the id would only
        // recognise a provider's single most expensive model, and the registry's default for
        // judgement tasks is the middle one — so every fallback would land on the cheap tier.
        return $task->prefersCapableModel()
            ? $connected->first()->capableModel()
            : $connected->first()->cheapestModel();
    }

    /** @return Collection<int, LlmProvider> */
    private function connectedProviders(int $accountId): Collection
    {
        return $this->integrations->connectedProviders($accountId)
            ->map(static fn (IntegrationProvider $provider): ?LlmProvider => LlmProvider::tryFrom($provider->value))
            ->filter()
            ->values();
    }

    /**
     * Config answers first because it is the only source with a price. Failing that, the
     * account's live catalogue answers: a model the provider lists but this deployment never
     * priced is still callable, and refusing it here would make the whole live list decorative.
     */
    public function providerFor(string $model, ?int $accountId = null): LlmProvider
    {
        return collect(LlmProvider::cases())
            ->first(static fn (LlmProvider $provider): bool => $provider->priceOf($model) !== null)
            ?? $this->liveProviderFor($model, $accountId)
            ?? throw UnknownLlmModelException::withModel($model);
    }

    private function liveProviderFor(string $model, ?int $accountId): ?LlmProvider
    {
        return $accountId === null ? null : collect(LlmProvider::cases())
            ->first(fn (LlmProvider $provider): bool => $this->catalog->idsFor($accountId, $provider)->contains($model));
    }

    private function effectiveTask(AiTask $task, int $accountId): AiTask
    {
        return $this->settings->get(self::SAME_FOR_ALL_KEY, $accountId) === true ? AiTask::Chat : $task;
    }
}
