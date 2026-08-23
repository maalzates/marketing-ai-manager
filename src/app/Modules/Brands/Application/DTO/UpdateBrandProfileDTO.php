<?php

declare(strict_types=1);

namespace App\Modules\Brands\Application\DTO;

use App\Modules\Brands\Domain\Enums\BrandKind;

readonly class UpdateBrandProfileDTO
{
    /**
     * @param  list<string>|null  $values
     * @param  list<string>|null  $bannedTopics
     * @param  list<array<string, mixed>>|null  $buyerPersonas
     * @param  list<string>|null  $referenceCompetitors
     * @param  list<string>|null  $brandColors
     */
    public function __construct(
        public int $accountId,
        public int $brandProfileId,
        public ?string $name = null,
        public ?BrandKind $kind = null,
        public ?string $description = null,
        public ?string $niche = null,
        public ?string $valueProposition = null,
        public ?string $toneOfVoice = null,
        public ?array $values = null,
        public ?array $bannedTopics = null,
        public ?array $buyerPersonas = null,
        public ?array $referenceCompetitors = null,
        public ?array $brandColors = null,
    ) {}
}
