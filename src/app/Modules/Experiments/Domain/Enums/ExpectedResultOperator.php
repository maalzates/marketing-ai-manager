<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Domain\Enums;

enum ExpectedResultOperator: string
{
    case Lte = 'lte';
    case Gte = 'gte';

    public function isSatisfiedBy(float $actual, float $threshold): bool
    {
        return match ($this) {
            self::Lte => $actual <= $threshold,
            self::Gte => $actual >= $threshold,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Lte => 'como máximo',
            self::Gte => 'como mínimo',
        };
    }
}
