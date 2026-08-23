<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\DTO;

use App\Modules\Experiments\Domain\Enums\Verdict;
use App\Modules\Experiments\Domain\ValueObjects\ExpectedResult;

readonly class SuggestedVerdictDTO
{
    public function __construct(
        public int $experimentId,
        public Verdict $verdict,
        public string $reasoning,
        public ExpectedResult $expected,
        public ?float $actualValue,
        public int $daysWithData,
    ) {}
}
