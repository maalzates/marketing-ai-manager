<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\ValueObjects;

use App\Modules\Experiments\Domain\Enums\ExpectedResultOperator;
use App\Modules\Experiments\Domain\Exceptions\InvalidExpectedResultException;

/**
 * The contract that turns a campaign into an experiment: which metric, which direction,
 * which threshold. All three parts or none — a partial one cannot produce a verdict.
 */
readonly class ExpectedResult
{
    /**
     * Metrics whose value is money spent per outcome, and therefore the ones Meta's
     * minimum-daily-budget arithmetic applies to.
     *
     * @var list<string>
     */
    private const array COST_PER_ACTION_METRICS = ['cpa', 'cpl', 'cost_per_lead', 'cost_per_follower'];

    public function __construct(
        public string $metric,
        public ExpectedResultOperator $operator,
        public float $value,
    ) {}

    public static function fromArray(array $expectedResult): self
    {
        return self::isComplete($expectedResult)
            ? new self(
                (string) $expectedResult['metric'],
                ExpectedResultOperator::from((string) $expectedResult['operator']),
                (float) $expectedResult['value'],
            )
            : throw InvalidExpectedResultException::malformed($expectedResult);
    }

    public static function isComplete(?array $expectedResult): bool
    {
        return is_string($expectedResult['metric'] ?? null)
            && $expectedResult['metric'] !== ''
            && ExpectedResultOperator::tryFrom((string) ($expectedResult['operator'] ?? '')) !== null
            && is_numeric($expectedResult['value'] ?? null);
    }

    public function isCostPerAction(): bool
    {
        return in_array($this->metric, self::COST_PER_ACTION_METRICS, true);
    }

    public function isSatisfiedBy(float $actual): bool
    {
        return $this->operator->isSatisfiedBy($actual, $this->value);
    }

    public function toArray(): array
    {
        return ['metric' => $this->metric, 'operator' => $this->operator->value, 'value' => $this->value];
    }
}
