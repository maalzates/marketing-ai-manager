<?php

declare(strict_types=1);

namespace App\Modules\Proposals\Application\DTO;

use App\Modules\Proposals\Domain\Enums\ProposalOrigin;
use App\Modules\Proposals\Domain\Enums\ProposalType;
use Carbon\CarbonImmutable;

readonly class ProposeDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $accountId,
        public ?int $userId,
        public ProposalType $type,
        public ProposalOrigin $origin,
        public string $title,
        public string $rationale,
        public array $payload = [],
        public ?int $strategyId = null,
        public ?int $experimentId = null,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
