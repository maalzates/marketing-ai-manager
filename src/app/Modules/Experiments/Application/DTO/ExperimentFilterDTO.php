<?php

declare(strict_types=1);

namespace App\Modules\Experiments\Application\DTO;

use App\Modules\Experiments\Domain\Enums\ExperimentStatus;
use App\Modules\Experiments\Domain\Enums\ExperimentType;
use App\Modules\Experiments\Domain\Enums\Verdict;

readonly class ExperimentFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?int $strategyId,
        public ?ExperimentStatus $status,
        public ?ExperimentType $type,
        public ?Verdict $verdict,
        public int $perPage,
        public int $page,
    ) {}
}
