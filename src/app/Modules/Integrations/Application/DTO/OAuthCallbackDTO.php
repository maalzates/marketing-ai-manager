<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\DTO;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

readonly class OAuthCallbackDTO
{
    public function __construct(
        public IntegrationProvider $provider,
        public ?string $code,
        public ?string $state,
        public ?string $error,
    ) {}
}
