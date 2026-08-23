<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Application\DTO;

use App\Modules\Integrations\Domain\Enums\IntegrationProvider;
use Carbon\CarbonInterface;

readonly class StoreOAuthCredentialsDTO
{
    /**
     * @param  array<string, string>  $credentials
     * @param  list<string>  $scopes
     */
    public function __construct(
        public int $accountId,
        public IntegrationProvider $provider,
        public array $credentials,
        public array $scopes,
        public ?CarbonInterface $expiresAt,
        public ?string $externalAccountId,
    ) {}
}
