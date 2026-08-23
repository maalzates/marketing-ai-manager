<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application\Services;

use App\Modules\Ai\Domain\Exceptions\TokenBudgetExceededException;
use App\Modules\Audit\Application\Services\UsageService;
use App\Modules\Settings\Application\Services\SettingsService;

/**
 * A hard stop, not a warning. The keys are the user's own, so a runaway loop bills their
 * card directly — the budget has to be enforced before the call leaves, in the one place
 * every door funnels through.
 */
readonly class TokenBudgetGuard
{
    private const string DAILY_LIMIT_KEY = 'ai.budget.daily_tokens';

    private const string MONTHLY_LIMIT_KEY = 'ai.budget.monthly_tokens';

    public function __construct(private SettingsService $settings, private UsageService $usage) {}

    public function assertWithinBudget(int $accountId, int $estimatedTokens): void
    {
        $this->assertPeriod('daily', self::DAILY_LIMIT_KEY, $accountId, $this->usage->spentToday($accountId), $estimatedTokens);
        $this->assertPeriod('monthly', self::MONTHLY_LIMIT_KEY, $accountId, $this->usage->spentThisMonth($accountId), $estimatedTokens);
    }

    private function assertPeriod(string $period, string $key, int $accountId, int $spent, int $estimatedTokens): void
    {
        $limit = (int) $this->settings->get($key, $accountId);

        // A limit of zero is how the settings registry spells "no cap".
        if ($limit > 0 && $spent + $estimatedTokens > $limit) {
            throw TokenBudgetExceededException::forPeriod($period, $limit, $spent, $estimatedTokens);
        }
    }
}
