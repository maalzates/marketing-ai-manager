<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Application\Services;

use App\Modules\Audit\Application\DTO\RecordActionDTO;
use App\Modules\Audit\Application\Services\ActionLogService;
use App\Modules\Audit\Domain\Enums\ActionOrigin;
use App\Modules\Integrations\Application\Services\IntegrationService;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Integrations\Domain\Enums\IntegrationStatus;
use App\Modules\Integrations\Infrastructure\Persistence\Integration;
use App\Modules\Onboarding\Application\DTO\OnboardingStepDTO;
use App\Modules\Onboarding\Domain\Contracts\OnboardingStateRepositoryInterface;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatus;
use App\Modules\Onboarding\Domain\Exceptions\OnboardingProviderRequiredException;
use App\Modules\Onboarding\Domain\Exceptions\OnboardingStepVerificationFailedException;
use App\Modules\Onboarding\Domain\Support\OnboardingProgress;
use App\Modules\Onboarding\Infrastructure\Persistence\OnboardingState;
use Illuminate\Support\Collection;

readonly class OnboardingService
{
    public function __construct(
        private OnboardingStateRepositoryInterface $repository,
        private IntegrationService $integrations,
        private OnboardingGuideProvider $guides,
        private ActionLogService $actionLog,
    ) {}

    /**
     * Created on demand rather than at signup, so an account that predates the wizard —
     * or one whose creation happened outside the auth flow — still gets one.
     */
    public function forAccount(int $accountId): OnboardingState
    {
        return $this->repository->firstOrCreateForAccount($accountId, OnboardingProgress::fresh()->toStored());
    }

    /** @return Collection<string, mixed> */
    public function state(int $accountId): Collection
    {
        $state = $this->forAccount($accountId);
        $progress = OnboardingProgress::fromStored($state->steps);
        $guides = $this->guides->all();
        $integrations = $this->integrationsByProvider($accountId);

        return collect([
            'completed_at' => $state->completed_at?->toIso8601String(),
            'resume_step' => $progress->firstPending()?->value,
            'steps' => collect(OnboardingStep::ordered())->map(fn (OnboardingStep $step): array => [
                'step' => $step->value,
                'label' => $step->label(),
                'unlocks' => $step->unlocks(),
                'status' => $progress->statusOf($step)->value,
                'blocked' => $progress->isBlocked($step),
                'provider' => $progress->providerOf($step)?->value,
                'changed_at' => $progress->changedAtOf($step),
                'guides' => $guides->only($step->guideKeys())->values(),
                'integrations' => $integrations->only($step->providerValues())->values(),
            ]),
        ]);
    }

    /** A step completes only on a live call the provider answered; nothing else counts. */
    public function completeStep(OnboardingStepDTO $dto): OnboardingState
    {
        $integration = $this->integrations->verify($dto->accountId, self::providerFor($dto));

        return $integration->status === IntegrationStatus::CONNECTED
            ? $this->record($dto, OnboardingStepStatus::COMPLETED, $integration->provider)
            : throw OnboardingStepVerificationFailedException::forStep(
                $dto->step,
                $integration,
                $this->guides->docsUrl($integration->provider),
            );
    }

    /** Skipping is a decision, not a failure: the feature stays locked and the CTA reopens this step. */
    public function skipStep(OnboardingStepDTO $dto): OnboardingState
    {
        return $this->record($dto, OnboardingStepStatus::SKIPPED, null);
    }

    public function resume(int $accountId): ?string
    {
        return OnboardingProgress::fromStored($this->forAccount($accountId)->steps)->firstPending()?->value;
    }

    public function complete(int $accountId): OnboardingState
    {
        return $this->completeWhenResolved($this->forAccount($accountId));
    }

    /**
     * The dashboard's permanent checklist. It reports what is connected *now*, not what was
     * connected once: a key revoked last week has to surface here or its jobs fail unseen.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function checklist(int $accountId): Collection
    {
        $progress = OnboardingProgress::fromStored($this->forAccount($accountId)->steps);
        $integrations = $this->integrationsByProvider($accountId);

        return collect(OnboardingStep::ordered())->map(function (OnboardingStep $step) use ($progress, $integrations): array {
            $forStep = $integrations->only($step->providerValues());
            $connected = $forStep->contains(
                fn (Integration $integration): bool => $integration->status === IntegrationStatus::CONNECTED,
            );

            return [
                'step' => $step->value,
                'label' => $step->label(),
                'unlocks' => $step->unlocks(),
                'status' => $progress->statusOf($step)->value,
                'connected' => $connected,
                'broken' => ! $connected && $progress->statusOf($step) === OnboardingStepStatus::COMPLETED,
                'needs_attention' => ! $connected,
                'integrations' => $forStep->values(),
            ];
        });
    }

    private function record(
        OnboardingStepDTO $dto,
        OnboardingStepStatus $status,
        ?IntegrationProvider $provider,
    ): OnboardingState {
        $state = $this->forAccount($dto->accountId);

        $this->actionLog->record(new RecordActionDTO(
            $dto->accountId,
            $dto->userId,
            "onboarding.step.{$status->value}",
            ActionOrigin::UI,
            ['step' => $dto->step->value, 'provider' => $provider?->value],
            'onboarding_state',
            $state->id,
        ));

        return $this->completeWhenResolved($this->repository->replaceSteps(
            $state,
            OnboardingProgress::fromStored($state->steps)->with($dto->step, $status, $provider)->toStored(),
        ));
    }

    private function completeWhenResolved(OnboardingState $state): OnboardingState
    {
        return $state->completed_at === null && OnboardingProgress::fromStored($state->steps)->isFullyResolved()
            ? $this->repository->markCompleted($state)
            : $state;
    }

    /** @return Collection<string, Integration> */
    private function integrationsByProvider(int $accountId): Collection
    {
        return $this->integrations->list($accountId)
            ->keyBy(fn (Integration $integration): string => $integration->provider->value);
    }

    private static function providerFor(OnboardingStepDTO $dto): IntegrationProvider
    {
        $allowed = $dto->step->providers();

        return match (true) {
            $dto->provider === null && count($allowed) === 1 => $allowed[0],
            $dto->provider === null => throw OnboardingProviderRequiredException::forStep($dto->step),
            in_array($dto->provider, $allowed, true) => $dto->provider,
            default => throw OnboardingProviderRequiredException::notPartOfStep($dto->step, $dto->provider),
        };
    }
}
