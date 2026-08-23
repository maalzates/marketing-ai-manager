<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\DTO;

readonly class MetaTokensDTO
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public ?int $expiresIn,
    ) {}
}
