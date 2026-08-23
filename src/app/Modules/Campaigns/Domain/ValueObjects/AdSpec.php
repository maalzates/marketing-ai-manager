<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Domain\ValueObjects;

readonly class AdSpec
{
    public function __construct(
        public string $name,
        public string $externalAdSetId,
        public string $externalCreativeId,
        public ?string $conversionDomain = null,
    ) {}
}
