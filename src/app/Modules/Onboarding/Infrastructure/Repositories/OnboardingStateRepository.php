<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Repositories;

use App\Modules\Onboarding\Domain\Contracts\OnboardingStateRepositoryInterface;
use App\Modules\Onboarding\Domain\Exceptions\OnboardingStateWriteFailedException;
use App\Modules\Onboarding\Infrastructure\Persistence\OnboardingState;
use Throwable;

readonly class OnboardingStateRepository implements OnboardingStateRepositoryInterface
{
    public function __construct(private OnboardingState $model) {}

    public function firstOrCreateForAccount(int $accountId, array $steps): OnboardingState
    {
        try {
            return $this->model->newQuery()->firstOrCreate(['account_id' => $accountId], ['steps' => $steps]);
        } catch (Throwable $exception) {
            throw OnboardingStateWriteFailedException::wrap($exception, context: ['account_id' => $accountId]);
        }
    }

    public function replaceSteps(OnboardingState $state, array $steps): OnboardingState
    {
        try {
            $state->update(['steps' => $steps]);

            return $state->refresh();
        } catch (Throwable $exception) {
            throw OnboardingStateWriteFailedException::wrap($exception, context: ['account_id' => $state->account_id]);
        }
    }

    public function markCompleted(OnboardingState $state): OnboardingState
    {
        try {
            $state->update(['completed_at' => now()]);

            return $state->refresh();
        } catch (Throwable $exception) {
            throw OnboardingStateWriteFailedException::wrap($exception, context: ['account_id' => $state->account_id]);
        }
    }
}
