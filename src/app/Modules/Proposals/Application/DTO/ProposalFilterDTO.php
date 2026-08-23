<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\DTO;

use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalStatus;
use App\Modules\Proposals\Domain\Enums\ProposalType;

readonly class ProposalFilterDTO
{
    public function __construct(
        public int $accountId,
        public ?ProposalStatus $status,
        public ?ProposalType $type,
        public ?ProposalOrigin $origin,
        public ?int $strategyId,
        public ?int $experimentId,
        public int $perPage,
        public int $page,
    ) {}
}
