<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\DTO;

readonly class GoogleTokensDTO
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public int $expiresIn,
        public string $tokenType,
        public ?string $idToken,
        /** @var list<string> */
        public array $scopes,
    ) {}
}
