<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\DTO;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;

readonly class ConnectApiKeyDTO
{
    public function __construct(
        public int $accountId,
        public IntegrationProvider $provider,
        public string $apiKey,
    ) {}
}
