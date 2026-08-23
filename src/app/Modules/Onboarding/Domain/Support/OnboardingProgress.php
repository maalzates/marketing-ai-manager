<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Domain\Support;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatus;
use Illuminate\Support\Collection;

/**
 * The persisted `steps` json, read back as the four core steps in their fixed order. It is
 * rebuilt from the enum on every load rather than trusted as stored, so a state written
 * before a step existed still answers for every step instead of half of them.
 */
readonly class OnboardingProgress
{
    /** @param Collection<string, array{status: OnboardingStepStatus, provider: ?IntegrationProvider, changed_at: ?string}> $entries */
    private function __construct(private Collection $entries) {}

    /** @param array<string, mixed> $steps */
    public static function fromStored(array $steps): self
    {
        return new self(collect(OnboardingStep::ordered())->mapWithKeys(
            fn (OnboardingStep $step): array => [$step->value => [
                'status' => OnboardingStepStatus::tryFrom((string) data_get($steps, $step->value.'.status'))
                    ?? OnboardingStepStatus::PENDING,
                'provider' => IntegrationProvider::tryFrom((string) data_get($steps, $step->value.'.provider')),
                'changed_at' => data_get($steps, $step->value.'.changed_at'),
            ]],
        ));
    }

    public static function fresh(): self
    {
        return self::fromStored([]);
    }

    public function statusOf(OnboardingStep $step): OnboardingStepStatus
    {
        return $this->entries[$step->value]['status'];
    }

    public function providerOf(OnboardingStep $step): ?IntegrationProvider
    {
        return $this->entries[$step->value]['provider'];
    }

    public function changedAtOf(OnboardingStep $step): ?string
    {
        return $this->entries[$step->value]['changed_at'];
    }

    /** The wizard walks one integration at a time: a step opens once every earlier one is resolved. */
    public function isBlocked(OnboardingStep $step): bool
    {
        return collect(OnboardingStep::ordered())
            ->takeWhile(fn (OnboardingStep $candidate): bool => $candidate !== $step)
            ->contains(fn (OnboardingStep $earlier): bool => ! $this->statusOf($earlier)->isResolved());
    }

    public function firstPending(): ?OnboardingStep
    {
        return collect(OnboardingStep::ordered())
            ->first(fn (OnboardingStep $step): bool => ! $this->statusOf($step)->isResolved());
    }

    public function isFullyResolved(): bool
    {
        return $this->firstPending() === null;
    }

    public function with(OnboardingStep $step, OnboardingStepStatus $status, ?IntegrationProvider $provider): self
    {
        return new self($this->entries->merge([$step->value => [
            'status' => $status,
            'provider' => $provider,
            'changed_at' => now()->toIso8601String(),
        ]]));
    }

    /** @return array<string, array{status: string, provider: ?string, changed_at: ?string}> */
    public function toStored(): array
    {
        return $this->entries
            ->map(fn (array $entry): array => [
                'status' => $entry['status']->value,
                'provider' => $entry['provider']?->value,
                'changed_at' => $entry['changed_at'],
            ])
            ->all();
    }
}
