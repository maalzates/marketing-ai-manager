<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Accounts\Infrastructure\Persistence\Account;
use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use App\Modules\Onboarding\Domain\Enums\OnboardingStep;
use App\Modules\Onboarding\Domain\Enums\OnboardingStepStatus;
use App\Modules\Onboarding\Domain\Support\OnboardingProgress;
use App\Modules\Onboarding\Infrastructure\Persistence\OnboardingState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingState>
 */
class OnboardingStateFactory extends Factory
{
    protected $model = OnboardingState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'steps' => OnboardingProgress::fresh()->toStored(),
            'completed_at' => null,
        ];
    }

    public function withStep(
        OnboardingStep $step,
        OnboardingStepStatus $status,
        ?IntegrationProvider $provider = null,
    ): static {
        return $this->state(fn (array $attributes): array => [
            'steps' => OnboardingProgress::fromStored($attributes['steps'])
                ->with($step, $status, $provider)
                ->toStored(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'steps' => collect(OnboardingStep::ordered())
                ->reduce(
                    fn (OnboardingProgress $progress, OnboardingStep $step): OnboardingProgress => $progress->with(
                        $step,
                        OnboardingStepStatus::COMPLETED,
                        $step->providers()[0],
                    ),
                    OnboardingProgress::fresh(),
                )
                ->toStored(),
            'completed_at' => now(),
        ]);
    }
}
